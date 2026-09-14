<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\Services\TenantRegistrySynchronizer;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finalize a legacy SIMAK cutover tenant: sanitize the migrated name,
 * provision the first admin user, and activate the tenant.
 *
 * @see docs/CUTOVER_RUNBOOK.md
 */
final class FinalizeTenantCutover extends Command
{
    protected $signature = 'tenancy:finalize-cutover
        {tenant : Tenant code or row id}
        {--password= : Explicit password for the provisioned admin user}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Finalize a cutover tenant: sanitize its name, provision an admin user, and activate it.';

    public function handle(
        TenantWorkbench $workbench,
        PermissionChecker $permissions,
        TenantRegistrySynchronizer $registry,
    ): int {
        $tenantArg = (string) $this->argument('tenant');

        $tenant = Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($tenantArg),
                fn ($query) => $query->whereKey((int) $tenantArg),
                fn ($query) => $query->where('code', $tenantArg),
            )
            ->first();

        if ($tenant === null) {
            $this->error("Tenant [{$tenantArg}] does not exist.");

            return self::FAILURE;
        }

        if ($tenant->placement === null || $tenant->placement->shard === null) {
            $this->error("Tenant [{$tenant->code}] has no complete placement.shard — cannot finalize.");

            return self::FAILURE;
        }

        try {
            $parity = $workbench->run($tenant, fn (): array => $this->latestParity((int) $tenant->row_id));
        } catch (Throwable $exception) {
            $this->error("Unable to read reconciliation results for tenant [{$tenant->code}]: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if (! $parity['ok']) {
            $this->parityError($tenant, $parity);

            return self::FAILURE;
        }

        $oldName = (string) $tenant->name;
        $newName = $this->sanitizeName($oldName);

        $admin = User::query()
            ->where('tenant_id', $tenant->row_id)
            ->where('status', 'active')
            ->orderBy('row_id')
            ->first();

        if (! (bool) $this->option('force')) {
            $this->summarize($tenant, $oldName, $newName, (int) $parity['batch'], $admin === null);

            if (! $this->confirm('Finalize this tenant?')) {
                $this->info('Aborted. No changes were made.');

                return self::INVALID;
            }
        }

        if ($newName !== $oldName) {
            $tenant->forceFill(['name' => $newName])->save();
        }

        $generatedPassword = null;

        if ($admin === null) {
            $username = $this->uniqueUsername($newName);
            $explicitPassword = (string) $this->option('password');
            $generatedPassword = $explicitPassword !== '' ? null : Str::random(16);
            $password = $generatedPassword ?? $explicitPassword;
            $publicId = (string) Str::ulid();

            $admin = User::query()->create([
                'public_id' => $publicId,
                'tenant_id' => $tenant->row_id,
                'name' => $newName,
                'email' => "{$username}@tenant.akubumdes.local",
                // users.phone is NOT NULL + unique; deterministic non-OTP sentinel
                // (same placeholder as the phone migration backfill / HoldingSsoController).
                'phone' => 'pending-wa-'.substr(md5($publicId), 0, 8),
                'username' => $username,
                'password' => Hash::make($password),
                'status' => 'active',
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->row_id,
                'user_id' => $admin->row_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        $workbench->run($tenant, function (Tenant $tenant) use ($permissions, $registry, $admin): void {
            $permissions->ensureSystemRoles();
            $permissions->assignRole($admin, 'admin');

            $tenant->forceFill([
                'status' => 'active',
                'provisioned_at' => $tenant->provisioned_at ?? now(),
            ])->save();

            $registry->sync($tenant);
        });

        $this->newLine();

        if ($newName !== $oldName) {
            $this->info("Name sanitized: [{$oldName}] → [{$newName}]");
        }

        if ($generatedPassword !== null) {
            $this->info("Admin user created: [{$admin->username}] <{$admin->email}>");
            $this->line("Generated password: {$generatedPassword}");
        } elseif ($admin->wasRecentlyCreated) {
            $this->info("Admin user created: [{$admin->username}] <{$admin->email}> (password: as provided via --password)");
        } else {
            $this->info("Existing active user kept: [{$admin->username}] (admin role ensured)");
        }

        $this->info(sprintf(
            'Tenant [%s] status: %s (provisioned_at: %s)',
            $tenant->code,
            $tenant->status,
            $tenant->provisioned_at?->format('Y-m-d H:i:s') ?? '—',
        ));

        return self::SUCCESS;
    }

    /**
     * Latest-batch reconciliation parity for the tenant. Must run inside the
     * tenant workbench so the shard connection is bound.
     *
     * @return array{ok: bool, batch: ?int, failures: list<array{scope: string, status: string, source_count: int, target_count: int}>}
     */
    private function latestParity(int $tenantId): array
    {
        $connection = DB::connection((string) config('tenancy.tenant_connection', 'tenant'));

        $latestBatch = $connection->table('migration_reconciliation_results')
            ->where('tenant_id', $tenantId)
            ->max('batch_row_id');

        if ($latestBatch === null) {
            return ['ok' => false, 'batch' => null, 'failures' => []];
        }

        $failures = $connection->table('migration_reconciliation_results')
            ->where('tenant_id', $tenantId)
            ->where('batch_row_id', (int) $latestBatch)
            ->where('status', '!=', 'matched')
            ->orderBy('row_id')
            ->get(['scope', 'status', 'source_count', 'target_count'])
            ->map(static fn (object $row): array => [
                'scope' => (string) $row->scope,
                'status' => (string) $row->status,
                'source_count' => (int) $row->source_count,
                'target_count' => (int) $row->target_count,
            ])
            ->all();

        return ['ok' => $failures === [], 'batch' => (int) $latestBatch, 'failures' => $failures];
    }

    /**
     * @param  array{ok: bool, batch: ?int, failures: list<array{scope: string, status: string, source_count: int, target_count: int}>}  $parity
     */
    private function parityError(Tenant $tenant, array $parity): void
    {
        if ($parity['batch'] === null) {
            $this->error(
                "No reconciliation results found for tenant [{$tenant->code}] — ".
                'run the cutover chain (legacy:cutover-tenant) before finalizing.',
            );

            return;
        }

        $this->error(
            "Parity check failed for tenant [{$tenant->code}] — latest reconciliation ".
            "batch #{$parity['batch']} is not fully matched:",
        );

        foreach ($parity['failures'] as $failure) {
            $this->line(sprintf(
                '  - %s: %s (source: %d, target: %d)',
                $failure['scope'],
                $failure['status'],
                $failure['source_count'],
                $failure['target_count'],
            ));
        }

        $this->line('Re-run the cutover reconciliation (legacy:migrate-accounting) until every scope reports matched.');
    }

    /**
     * Strip HTML tags (e.g. legacy "<br>" separators), collapse whitespace, trim.
     * Text content is preserved — only tags and redundant whitespace are removed.
     */
    private function sanitizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($name)));
    }

    /**
     * Username from the tenant name slug, capped at 30 chars, unique platform-wide.
     */
    private function uniqueUsername(string $name): string
    {
        $base = rtrim(substr(Str::slug($name), 0, 30), '-');

        if ($base === '') {
            $base = 'admin';
        }

        $username = $base;

        for ($suffix = 2; User::query()->where('username', $username)->exists(); $suffix++) {
            $username = substr($base, 0, 30 - strlen((string) $suffix) - 1).'-'.$suffix;
        }

        return $username;
    }

    private function summarize(Tenant $tenant, string $oldName, string $newName, int $batch, bool $willCreateUser): void
    {
        $this->info("Finalizing tenant [{$tenant->code}] (row_id {$tenant->row_id}):");

        if ($newName !== $oldName) {
            $this->line("  name: [{$oldName}] → [{$newName}]");
        }

        $this->line("  parity: latest reconciliation batch #{$batch} fully matched");

        $this->line($willCreateUser
            ? '  admin user: will be created (username derived from the tenant name slug)'
            : '  admin user: existing active user will be kept');

        $this->line("  status: {$tenant->status} → active");
    }
}
