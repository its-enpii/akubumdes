<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantFinalizeCutoverCommandTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->finalizeTenant = $this->createFinalizeTenant('33-03-11-2014', 'New Kospin Jaya <br> PROVINSI JAWA TENGAH <br>');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    protected Tenant $finalizeTenant;

    public function test_finalize_cutover_happy_path_activates_tenant_and_creates_admin(): void
    {
        $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'matched'],
            ['journal_lines_count', 'matched'],
            ['transaksi_sums', 'matched'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);

        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])
            ->expectsOutputToContain('Admin user created: [new-kospin-jaya-provinsi-jawa] <new-kospin-jaya-provinsi-jawa@tenant.akubumdes.local>')
            ->expectsOutputToContain('Generated password:')
            ->assertSuccessful();

        // Tenant sanitized, active, provisioned
        $tenant = Tenant::query()->where('code', '33-03-11-2014')->firstOrFail();
        self::assertSame('New Kospin Jaya PROVINSI JAWA TENGAH', $tenant->name);
        self::assertSame('active', $tenant->status);
        self::assertNotNull($tenant->provisioned_at);

        // Admin user created per ProvisionDevUser pattern
        $user = User::query()->where('tenant_id', $tenant->row_id)->where('status', 'active')->firstOrFail();
        // Username = slug of the sanitized name, capped at 30 chars, trailing '-' trimmed.
        self::assertSame('new-kospin-jaya-provinsi-jawa', $user->username);
        self::assertSame('new-kospin-jaya-provinsi-jawa@tenant.akubumdes.local', $user->email);
        self::assertSame(26, strlen((string) $user->public_id));
        self::assertNotSame('password', $user->password);
        self::assertLessThanOrEqual(30, strlen((string) $user->username));
        self::assertSame(
            rtrim(substr(Str::slug((string) $tenant->name), 0, 30), '-'),
            (string) $user->username,
        );
        self::assertStringStartsWith('pending-wa-', (string) $user->phone);

        // Membership active
        $membership = DB::connection('platform')->table('tenant_memberships')
            ->where('tenant_id', $tenant->row_id)
            ->where('user_id', $user->row_id)
            ->first();
        self::assertNotNull($membership);
        self::assertSame('active', $membership->status);

        // admin role assigned in shard
        $roles = app(TenantWorkbench::class)->run($tenant, fn (): array => UserRole::query()
            ->where('platform_user_id', $user->row_id)
            ->with('role:row_id,code')
            ->get()
            ->map(fn (UserRole $ur): ?string => $ur->role?->code)
            ->filter()
            ->values()
            ->all());
        self::assertContains('admin', $roles);

        // registry synced with sanitized name + active status
        $registryRow = DB::connection('tenant')->table('tenant_registry')
            ->where('id', $tenant->row_id)
            ->first();
        self::assertNotNull($registryRow);
        self::assertSame('New Kospin Jaya PROVINSI JAWA TENGAH', $registryRow->name);
        self::assertSame('active', $registryRow->status);
    }

    public function test_finalize_cutover_rejects_when_parity_not_matched(): void
    {
        $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'matched'],
            ['journal_lines_count', 'matched'],
            ['transaksi_sums', 'mismatch'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);

        $this->artisan('tenancy:finalize-cutover', ['tenant' => $this->finalizeTenant->row_id, '--force' => true])
            ->expectsOutputToContain('Parity check failed')
            ->assertFailed();

        $tenant = $this->finalizeTenant->fresh();
        self::assertSame('provisioning', $tenant->status);
        self::assertNull($tenant->provisioned_at);
        self::assertSame(0, User::query()->where('tenant_id', $tenant->row_id)->count());
    }

    public function test_finalize_cutover_sanitizes_name_with_html_tags(): void
    {
        $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'matched'],
            ['journal_lines_count', 'matched'],
            ['transaksi_sums', 'matched'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);

        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])
            ->expectsOutputToContain('Name sanitized: [New Kospin Jaya <br> PROVINSI JAWA TENGAH <br>] → [New Kospin Jaya PROVINSI JAWA TENGAH]')
            ->assertSuccessful();

        self::assertSame(
            'New Kospin Jaya PROVINSI JAWA TENGAH',
            Tenant::query()->where('code', '33-03-11-2014')->value('name'),
        );
    }

    public function test_finalize_cutover_is_idempotent_on_second_run(): void
    {
        $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'matched'],
            ['journal_lines_count', 'matched'],
            ['transaksi_sums', 'matched'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);

        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])->assertSuccessful();
        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])
            ->expectsOutputToContain('Existing active user kept')
            ->assertSuccessful();

        $tenant = Tenant::query()->where('code', '33-03-11-2014')->firstOrFail();

        // No duplicated users
        self::assertSame(1, User::query()->where('tenant_id', $tenant->row_id)->count());

        // No duplicated memberships
        self::assertSame(1, DB::connection('platform')->table('tenant_memberships')
            ->where('tenant_id', $tenant->row_id)
            ->count());

        // No duplicated admin role assignments in shard
        $count = app(TenantWorkbench::class)->run($tenant, fn (): int => UserRole::query()
            ->where('platform_user_id', User::query()->where('tenant_id', $tenant->row_id)->value('row_id'))
            ->count());
        self::assertSame(1, $count);

        // System roles not duplicated either
        $adminRoles = app(TenantWorkbench::class)->run($tenant, fn (): int => Role::query()
            ->where('code', 'admin')
            ->count());
        self::assertSame(1, $adminRoles);
    }

    public function test_finalize_cutover_uses_latest_batch_per_tenant(): void
    {
        // Older batch for this tenant contains a mismatch — must be ignored.
        $olderBatch = $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'mismatch'],
            ['journal_lines_count', 'matched'],
            ['transaksi_sums', 'mismatch'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);
        // Latest batch fully matched — this is the one the command must read.
        $latestBatch = $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'matched'],
            ['journal_lines_count', 'matched'],
            ['transaksi_sums', 'matched'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);

        self::assertGreaterThan($olderBatch, $latestBatch);

        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])
            ->assertSuccessful();

        self::assertSame('active', Tenant::query()->where('code', '33-03-11-2014')->value('status'));

        // The older, mismatched batch must be left untouched as evidence.
        self::assertSame(
            2,
            DB::connection('tenant')->table('migration_reconciliation_results')
                ->where('tenant_id', $this->finalizeTenant->row_id)
                ->where('batch_row_id', $olderBatch)
                ->where('status', '!=', 'matched')
                ->count(),
        );

        // Negative control: a newer mismatched batch must gate the finalize,
        // proving the gate reads max(batch_row_id) rather than "any matched batch".
        $newestBatch = $this->seedReconciliations($this->finalizeTenant->row_id, [
            ['transaksi_count', 'matched'],
            ['journal_lines_count', 'mismatch'],
            ['transaksi_sums', 'matched'],
            ['journals_balanced', 'matched'],
            ['openings', 'matched'],
        ]);

        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])
            ->expectsOutputToContain("latest reconciliation batch #{$newestBatch} is not fully matched")
            ->expectsOutputToContain('journal_lines_count: mismatch (source: 10, target: 7)')
            ->assertFailed();
    }

    public function test_finalize_cutover_rejects_unknown_tenant_and_missing_parity(): void
    {
        $this->artisan('tenancy:finalize-cutover', ['tenant' => 'nope', '--force' => true])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();

        // No reconciliation results at all → reject
        $this->artisan('tenancy:finalize-cutover', ['tenant' => '33-03-11-2014', '--force' => true])
            ->expectsOutputToContain('No reconciliation results found')
            ->assertFailed();
    }

    private function createFinalizeTenant(string $code, string $name): Tenant
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name,
            'district_code' => str_replace('-', '.', $code),
            'coa_variant' => 'cooperative',
            'status' => 'provisioning',
            'timezone' => 'Asia/Jakarta',
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $this->testShard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $tenant->row_id,
            'public_id' => $tenant->public_id,
            'code' => $tenant->code,
            'name' => $tenant->name,
            'status' => 'provisioning',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tenant;
    }

    /**
     * Seed one legacy migration batch plus its reconciliation rows.
     *
     * The command gates on parity by reading max(batch_row_id) for the tenant in
     * migration_reconciliation_results (FinalizeTenantCutover::latestParity), so
     * every call creates a NEW batch and returns its row_id — that is what lets a
     * test stack an older mismatched batch under a newer matched one.
     *
     * @param  list<array{string, string}>  $scopes
     * @return int row_id of the batch that was created
     */
    private function seedReconciliations(int $tenantId, array $scopes): int
    {
        // The batch row must exist first: reconciliation rows FK to legacy_migration_batches.row_id.
        DB::connection('tenant')->table('legacy_migration_batches')->insert([
            'tenant_id' => $tenantId,
            'public_id' => (string) Str::ulid(),
            'source_database' => 'akubumdes',
            'source_suffix' => '14',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchRowId = (int) DB::connection('tenant')->table('legacy_migration_batches')
            ->where('tenant_id', $tenantId)
            ->max('row_id');
        self::assertGreaterThan(0, $batchRowId);

        foreach ($scopes as [$scope, $status]) {
            DB::connection('tenant')->table('migration_reconciliation_results')->insert([
                'tenant_id' => $tenantId,
                'batch_row_id' => $batchRowId,
                'scope' => $scope,
                'status' => $status,
                'source_count' => 10,
                'target_count' => $status === 'matched' ? 10 : 7,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $batchRowId;
    }
}
