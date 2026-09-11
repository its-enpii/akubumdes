<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\Services\TenantRegistrySynchronizer;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class InitializeTenantSequences extends Command
{
    protected $signature = 'tenancy:initialize-sequences
        {tenant : Tenant row ID or code}
        {--dry-run : Display calculated values without updating sequence rows}';

    protected $description = 'Initialize per-tenant local ID sequences to MAX(id) + 1 after migration.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        TenantRegistrySynchronizer $registry,
        TenantSequenceService $sequences,
    ): int {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $connections->connect($shard);

        try {
            $registry->sync($tenant);
            $context->initialize($tenant, $placement, $shard);

            $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
            $schema = Schema::connection($connectionName);
            $db = DB::connection($connectionName);

            $tables = [
                'organization_profiles',
                'organization_units',
                'business_types',
                'activity_types',
                'accounts',
                'fiscal_periods',
                'journal_entries',
                'journal_lines',
                'account_opening_balances',
                'budgets',
                'budget_lines',
                'asset_categories',
                'assets',
                'asset_status_histories',
                'documents',
                'roles',
                'user_roles',
                'audit_logs',
            ];

            foreach ($tables as $table) {
                if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'id')) {
                    continue;
                }

                $maxId = (int) $db->table($table)
                    ->where('tenant_id', $tenant->row_id)
                    ->max('id');
                $next = $maxId + 1;

                $this->line(sprintf('%-36s %d', $table, $next));

                if (! $this->option('dry-run')) {
                    $sequences->initializeAtLeast($table, $next);
                }
            }

            $this->info($this->option('dry-run')
                ? 'Dry run completed; no sequence was updated.'
                : 'Tenant sequences initialized successfully.');

            return self::SUCCESS;
        } finally {
            $context->clear();
            $connections->disconnect();
        }
    }

    private function resolveTenant(string $value): Tenant
    {
        return Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('code', $value),
            )
            ->firstOrFail();
    }
}
