<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Accounting\LegacyCoaImporter;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\TenantWorkbench;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class ImportLegacyChartOfAccounts extends Command
{
    protected $signature = 'tenancy:import-legacy-chart-of-accounts
        {tenant : Tenant row ID or code}
        {--suffix= : Legacy suffix (lokasi / usaha ID)}
        {--dry-run : Show what would be imported without writing}
        {--reset : Wipe existing accounts for the tenant before importing (DANGEROUS)}
        {--skip-settings : Skip seeding default tenant settings (account.pencairan_*)}';

    protected $description = 'Import the legacy chart-of-accounts into the accounts table for the given tenant.';

    public function handle(TenantWorkbench $workbench, LegacyCoaImporter $importer): int
    {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $suffix = $this->option('suffix') ? (string) $this->option('suffix') : null;
        $dryRun = (bool) $this->option('dry-run');
        $reset = (bool) $this->option('reset');

        try {
            $workbench->run($tenant, function () use ($importer, $suffix, $dryRun, $reset): void {
                if ($dryRun) {
                    $preview = $importer->import(suffix: $suffix, dryRun: true);
                    $this->info("Source: {$preview['source']} (variant: {$preview['variant']})");
                    $this->info("Would insert: {$preview['inserted']}");
                    $this->info("Would update: {$preview['updated']}");
                    $this->info("Would skip: {$preview['skipped']}");

                    return;
                }

                if ($reset) {
                    $tenantId = app(TenantContext::class)->id();
                    if (! $this->confirm("Wipe all existing accounts for tenant {$tenantId}?", false)) {
                        $this->info('Aborted by user.');

                        return;
                    }
                }

                $result = $importer->import(suffix: $suffix, dryRun: false, reset: $reset);
                $this->info("Source: {$result['source']} (variant: {$result['variant']})");
                $this->info("Inserted: {$result['inserted']}");
                $this->info("Updated: {$result['updated']}");
                $this->info("Skipped: {$result['skipped']}");
            });
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
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
