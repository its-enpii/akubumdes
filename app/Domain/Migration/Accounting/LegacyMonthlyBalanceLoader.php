<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Migration\Accounting\DTO\NormalizedMonthly;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final class LegacyMonthlyBalanceLoader
{
    public function __construct(
        private TenantContext $context,
    ) {}

    /**
     * @param  list<NormalizedMonthly>  $monthlyBalances
     */
    public function load(int $batchRowId, string $sourceTable, array $monthlyBalances): int
    {
        if ($monthlyBalances === []) {
            return 0;
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $upserted = 0;

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $batchRowId,
            $sourceTable,
            $monthlyBalances,
            $now,
            &$upserted,
        ): void {
            foreach ($monthlyBalances as $m) {
                $db->table('account_monthly_balances')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'account_row_id' => $m->accountRowId,
                        'fiscal_year' => $m->fiscalYear,
                        'fiscal_month' => $m->fiscalMonth,
                    ],
                    [
                        'movement_debit' => $m->debit,
                        'movement_credit' => $m->credit,
                        'closing_debit' => $m->debit,
                        'closing_credit' => $m->credit,
                        'recalculated_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $row = $db->table('account_monthly_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('account_row_id', $m->accountRowId)
                    ->where('fiscal_year', $m->fiscalYear)
                    ->where('fiscal_month', $m->fiscalMonth)
                    ->first(['row_id']);

                $targetRowId = $row ? (int) $row->row_id : null;

                $existingMapping = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', $m->sourceId)
                    ->where('source_secondary_key', (string) $m->fiscalMonth)
                    ->first();

                if ($existingMapping === null) {
                    $db->table('legacy_record_mappings')->insert([
                        'tenant_id' => $tenantId,
                        'batch_row_id' => $batchRowId,
                        'source_table' => $sourceTable,
                        'source_id' => $m->sourceId,
                        'source_secondary_key' => (string) $m->fiscalMonth,
                        'target_table' => 'account_monthly_balances',
                        'target_row_id' => $targetRowId,
                        'target_local_id' => null,
                        'source_snapshot' => json_encode([
                            'kode_akun' => $m->accountCode,
                            'tahun' => $m->fiscalYear,
                            'bulan' => $m->fiscalMonth,
                            'debit' => $m->debit,
                            'credit' => $m->credit,
                        ], JSON_THROW_ON_ERROR),
                        'migrated_at' => $now,
                        'created_at' => $now,
                    ]);
                } else {
                    $db->table('legacy_record_mappings')
                        ->where('tenant_id', $tenantId)
                        ->where('source_table', $sourceTable)
                        ->where('source_id', $m->sourceId)
                        ->where('source_secondary_key', (string) $m->fiscalMonth)
                        ->update([
                            'batch_row_id' => $batchRowId,
                            'target_row_id' => $targetRowId,
                            'migrated_at' => $now,
                        ]);
                }

                $upserted++;
            }
        }, 5);

        return $upserted;
    }
}
