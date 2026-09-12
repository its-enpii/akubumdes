<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Migration\Support\LegacyConnection;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyCoaImporter
{
    public function __construct(
        private TenantContext $context,
        private LegacyConnection $legacy,
        private DefaultChartOfAccountsProvisioner $defaultProvisioner,
    ) {}

    /**
     * @return array{inserted: int, updated: int, skipped: int, source: string, variant: string}
     */
    public function import(?string $suffix = null, bool $dryRun = false, bool $reset = false): array
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required to import chart of accounts.');
        }

        $tenant = $this->context->tenant();
        $suffix = $suffix ?: $this->resolveSuffix($tenant);

        $variant = $tenant->coa_variant ?? 'standard';
        $jenisAkun = match ($variant) {
            'trading' => 7,
            'cooperative' => 8,
            default => 5,
        };

        // Try to read usaha from legacy if available
        if ($suffix !== null) {
            try {
                if ($this->legacy->tableExists('usaha')) {
                    $usaha = $this->legacy->selectOne(
                        'SELECT id, nama_usaha, jenis_akun, kd_desa FROM usaha WHERE id = ? LIMIT 1',
                        [(int) $suffix],
                    );
                    if ($usaha !== null && isset($usaha->jenis_akun)) {
                        $jenisAkun = (int) $usaha->jenis_akun;
                        $variant = match ($jenisAkun) {
                            7 => 'trading',
                            8 => 'cooperative',
                            default => 'standard',
                        };
                        if ($tenant->coa_variant !== $variant) {
                            $tenant->update(['coa_variant' => $variant]);
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore and use variant from tenant
            }
        }

        // Determine table names per variant & suffix
        $level1Table = match ($jenisAkun) {
            7 => 'akun_level_1s',
            8 => 'akun_level_1_koperasi',
            default => 'akun_level_1',
        };
        $level2Table = match ($jenisAkun) {
            7 => 'akun_level_2s',
            8 => 'akun_level_2_koperasi',
            default => 'akun_level_2',
        };
        $level3Table = $suffix !== null ? 'akun_'.$suffix : 'akun_1';
        $postingTable = $jenisAkun === 7
            ? ($suffix !== null ? 'accounts_'.$suffix : 'accounts_1')
            : ($suffix !== null ? 'rekening_'.$suffix : 'rekening_1');

        $hasLegacyTables = false;
        try {
            $hasLegacyTables = $this->legacy->tableExists($level1Table)
                || $this->legacy->tableExists($postingTable)
                || ($suffix !== null && $this->legacy->tableExists($level3Table));
        } catch (\Throwable) {
            $hasLegacyTables = false;
        }

        if (! $hasLegacyTables) {
            if ($dryRun) {
                $preview = $this->defaultProvisioner->preview();

                return [
                    'inserted' => $preview['would_insert'],
                    'updated' => 0,
                    'skipped' => $preview['would_skip'],
                    'source' => 'template',
                    'variant' => $variant,
                ];
            }
            if ($reset) {
                $this->defaultProvisioner->reset();
            }
            $res = $this->defaultProvisioner->ensureDefaults(seedSettings: true);

            return [
                'inserted' => $res['inserted'],
                'updated' => 0,
                'skipped' => $res['skipped'],
                'source' => 'template',
                'variant' => $variant,
            ];
        }

        // Extract accounts from legacy tables
        $legacyRows = $this->extractLegacyCoaRows($level1Table, $level2Table, $level3Table, $postingTable, $jenisAkun);

        if ($dryRun) {
            $tenantId = $this->context->id();
            $connName = (string) config('tenancy.tenant_connection', 'tenant');
            $existing = DB::connection($connName)->table('accounts')
                ->where('tenant_id', $tenantId)
                ->pluck('code')
                ->all();
            $existingSet = array_flip($existing);

            $wouldInsert = 0;
            $wouldUpdate = 0;
            foreach ($legacyRows as $r) {
                if (isset($existingSet[$r['code']])) {
                    $wouldUpdate++;
                } else {
                    $wouldInsert++;
                }
            }

            return [
                'inserted' => $wouldInsert,
                'updated' => $wouldUpdate,
                'skipped' => 0,
                'source' => 'legacy',
                'variant' => $variant,
            ];
        }

        if ($reset) {
            $this->defaultProvisioner->reset();
        }

        $result = $this->persistLegacyAccounts($legacyRows);
        $result['source'] = 'legacy';
        $result['variant'] = $variant;

        return $result;
    }

    /**
     * @return list<array{
     *   code: string,
     *   name: string,
     *   level: int,
     *   parent_code: string|null,
     *   normal_balance: string,
     *   is_postable: bool,
     *   is_active: bool,
     *   deactivated_at: string|null
     * }>
     */
    private function extractLegacyCoaRows(
        string $level1Table,
        string $level2Table,
        string $level3Table,
        string $postingTable,
        int $jenisAkun,
    ): array {
        $rowsByCode = [];

        // 1. Level 1
        if ($this->legacy->tableExists($level1Table)) {
            $l1Rows = $this->legacy->select("SELECT * FROM `{$level1Table}` ORDER BY id ASC");
            foreach ($l1Rows as $row) {
                $code = trim((string) ($row->kode_akun ?? $row->id ?? ''));
                if ($code === '') {
                    continue;
                }
                $rowsByCode[$code] = [
                    'code' => $code,
                    'name' => trim((string) ($row->nama_akun ?? $row->nama ?? $code)),
                    'level' => 1,
                    'parent_code' => null,
                    'normal_balance' => $this->deriveNormalBalance($code, $row->jenis_mutasi ?? null),
                    'is_postable' => false,
                    'is_active' => true,
                    'deactivated_at' => null,
                ];
            }
        }

        // 2. Level 2
        if ($this->legacy->tableExists($level2Table)) {
            $l2Rows = $this->legacy->select("SELECT * FROM `{$level2Table}` ORDER BY id ASC");
            foreach ($l2Rows as $row) {
                $code = trim((string) ($row->kode_akun ?? ''));
                if ($code === '') {
                    continue;
                }
                $parentCode = $this->deriveParentCode($code);
                $rowsByCode[$code] = [
                    'code' => $code,
                    'name' => trim((string) ($row->nama_akun ?? $row->nama ?? $code)),
                    'level' => 2,
                    'parent_code' => $parentCode,
                    'normal_balance' => $this->deriveNormalBalance($code, $row->jenis_mutasi ?? null),
                    'is_postable' => false,
                    'is_active' => true,
                    'deactivated_at' => null,
                ];
            }
        }

        // 3. Level 3 (from akun_{lokasi} or fallback)
        if ($this->legacy->tableExists($level3Table)) {
            $l3Rows = $this->legacy->select("SELECT * FROM `{$level3Table}` ORDER BY id ASC");
            foreach ($l3Rows as $row) {
                $code = trim((string) ($row->kode_akun ?? ''));
                if ($code === '') {
                    continue;
                }
                $parentCode = $this->deriveParentCode($code);
                $rowsByCode[$code] = [
                    'code' => $code,
                    'name' => trim((string) ($row->nama_akun ?? $row->nama ?? $code)),
                    'level' => 3,
                    'parent_code' => $parentCode,
                    'normal_balance' => $this->deriveNormalBalance($code, $row->jenis_mutasi ?? null),
                    'is_postable' => false,
                    'is_active' => true,
                    'deactivated_at' => null,
                ];
            }
        }

        // 4. Posting Level 4 (from rekening_{lokasi} or accounts_{lokasi})
        if ($this->legacy->tableExists($postingTable)) {
            $postRows = $this->legacy->select("SELECT * FROM `{$postingTable}` ORDER BY id ASC");
            foreach ($postRows as $row) {
                $code = trim((string) ($row->kode_akun ?? ''));
                if ($code === '') {
                    continue;
                }
                $parentCode = $this->deriveParentCode($code);
                $tglNonaktif = isset($row->tgl_nonaktif) && $row->tgl_nonaktif !== null ? trim((string) $row->tgl_nonaktif) : null;
                $isActive = true;
                $deactivatedAt = null;

                if ($tglNonaktif !== null && $tglNonaktif !== '' && $tglNonaktif !== '0000-00-00') {
                    $ts = strtotime($tglNonaktif);
                    if ($ts !== false && $ts <= time()) {
                        $isActive = false;
                        $deactivatedAt = date('Y-m-d', $ts);
                    }
                }

                $rowsByCode[$code] = [
                    'code' => $code,
                    'name' => trim((string) ($row->nama_akun ?? $row->nama ?? $code)),
                    'level' => max(4, substr_count($code, '.') + 1),
                    'parent_code' => $parentCode,
                    'normal_balance' => $this->deriveNormalBalance($code, $row->jenis_mutasi ?? null),
                    'is_postable' => true,
                    'is_active' => $isActive,
                    'deactivated_at' => $deactivatedAt,
                ];
            }
        }

        // Ensure synthesized missing parent levels exist in the hierarchy
        $allCodes = array_keys($rowsByCode);
        foreach ($allCodes as $rawCode) {
            $c = (string) $rawCode;
            $parts = explode('.', $c);
            for ($i = count($parts) - 1; $i >= 1; $i--) {
                $ancestor = implode('.', array_slice($parts, 0, $i));
                if (! isset($rowsByCode[$ancestor])) {
                    $ancestorParent = $i > 1 ? implode('.', array_slice($parts, 0, $i - 1)) : null;
                    $rowsByCode[$ancestor] = [
                        'code' => $ancestor,
                        'name' => 'Akun '.$ancestor,
                        'level' => $i,
                        'parent_code' => $ancestorParent,
                        'normal_balance' => $this->deriveNormalBalance($ancestor, null),
                        'is_postable' => false,
                        'is_active' => true,
                        'deactivated_at' => null,
                    ];
                }
            }
        }

        return array_values($rowsByCode);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{inserted: int, updated: int, skipped: int}
     */
    private function persistLegacyAccounts(array $rows): array
    {
        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $inserted = 0;
        $updated = 0;

        DB::connection($connName)->transaction(function () use ($rows, &$inserted, &$updated): void {
            // Sort rows by level ascending, then code ascending
            usort($rows, static fn ($a, $b) => $a['level'] <=> $b['level'] ?: strcmp($a['code'], $b['code']));

            $existing = Account::query()->get()->keyBy('code');
            $byCode = [];
            foreach ($existing as $c => $acc) {
                $byCode[$c] = (int) $acc->row_id;
            }

            foreach ($rows as $row) {
                $code = (string) $row['code'];
                $parentCode = $row['parent_code'] ? (string) $row['parent_code'] : null;
                $parentRowId = null;

                if ($parentCode !== null && isset($byCode[$parentCode])) {
                    $parentRowId = $byCode[$parentCode];
                }

                if (isset($existing[$code])) {
                    $acc = $existing[$code];
                    $acc->update([
                        'name' => trim((string) $row['name']),
                        'account_type' => $this->mapAccountType($code),
                        'normal_balance' => (string) $row['normal_balance'],
                        'level' => (int) $row['level'],
                        'is_postable' => (bool) $row['is_postable'],
                        'is_active' => (bool) $row['is_active'],
                        'deactivated_at' => $row['deactivated_at'],
                        'parent_row_id' => $parentRowId ?? $acc->parent_row_id,
                        'legacy_parent_code' => $parentCode,
                    ]);
                    $byCode[$code] = (int) $acc->row_id;
                    $updated++;
                } else {
                    $acc = Account::query()->create([
                        'code' => $code,
                        'name' => trim((string) $row['name']),
                        'account_type' => $this->mapAccountType($code),
                        'normal_balance' => (string) $row['normal_balance'],
                        'level' => (int) $row['level'],
                        'is_postable' => (bool) $row['is_postable'],
                        'is_active' => (bool) $row['is_active'],
                        'deactivated_at' => $row['deactivated_at'],
                        'parent_row_id' => $parentRowId,
                        'legacy_parent_code' => $parentCode,
                    ]);
                    $existing[$code] = $acc;
                    $byCode[$code] = (int) $acc->row_id;
                    $inserted++;
                }
            }
        });

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => 0,
        ];
    }

    private function deriveParentCode(string $code): ?string
    {
        $lastDot = strrpos($code, '.');
        if ($lastDot === false) {
            return null;
        }

        return substr($code, 0, $lastDot);
    }

    private function deriveNormalBalance(string $code, mixed $jenisMutasi): string
    {
        if ($jenisMutasi !== null) {
            $jm = strtolower(trim((string) $jenisMutasi));
            if ($jm === 'debet' || $jm === 'd' || $jm === 'debit') {
                return 'D';
            }
            if ($jm === 'kredit' || $jm === 'c' || $jm === 'k' || $jm === 'credit') {
                return 'C';
            }
        }

        $prefix = explode('.', $code)[0];

        return match ($prefix) {
            '1', '5', '6' => 'D',
            '2', '3', '4' => 'C',
            '7' => str_starts_with($code, '7.4') ? 'D' : 'C',
            default => 'D',
        };
    }

    private function mapAccountType(string $code): string
    {
        $prefix = explode('.', $code)[0].'.';

        return match ($prefix) {
            '1.' => 'asset',
            '2.' => 'liability',
            '3.' => 'equity',
            '4.' => 'revenue',
            '5.' => 'expense',
            '6.' => 'expense',
            '7.' => str_starts_with($code, '7.4') ? 'expense' : 'revenue',
            default => 'unknown',
        };
    }

    private function resolveSuffix(Tenant $tenant): ?string
    {
        if ($tenant->district_code) {
            try {
                if ($this->legacy->tableExists('usaha')) {
                    $usaha = $this->legacy->selectOne(
                        'SELECT id FROM usaha WHERE kd_desa = ? LIMIT 1',
                        [$tenant->district_code],
                    );
                    if ($usaha !== null) {
                        return (string) $usaha->id;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if (is_numeric($tenant->code)) {
            return (string) $tenant->code;
        }

        return null;
    }
}
