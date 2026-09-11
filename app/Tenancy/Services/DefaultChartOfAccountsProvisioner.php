<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Domain\Accounting\Models\Account;
use App\Tenancy\Services\Coa\Templates\CoaTemplate;
use App\Tenancy\Services\Coa\Templates\CooperativeCoaTemplate;
use App\Tenancy\Services\Coa\Templates\StandardCoaTemplate;
use App\Tenancy\Services\Coa\Templates\TradingCoaTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Idempotent default COA seed. Requires TenantContext. */
final class DefaultChartOfAccountsProvisioner
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly StandardCoaTemplate $standardTemplate,
        private readonly TradingCoaTemplate $tradingTemplate,
        private readonly CooperativeCoaTemplate $cooperativeTemplate,
    ) {}

    /**
     * @return array{inserted: int, skipped: int, settings_seeded: int}
     */
    public function ensureDefaults(bool $seedSettings = true): array
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required to provision chart of accounts.');
        }

        $tenantId = $this->context->id();
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $byCode = [];
        $inserted = 0;
        $skipped = 0;

        DB::connection($connectionName)->transaction(function () use ($tenantId, &$byCode, &$inserted, &$skipped): void {
            foreach ($this->template()->rows() as $row) {
                $existing = $this->existingAccount($tenantId, $row['code']);
                if ($existing !== null) {
                    $byCode[$row['code']] = (int) $existing['row_id'];
                    $skipped++;

                    continue;
                }

                $parentRowId = null;
                if ($row['parent_code'] !== null) {
                    if (! isset($byCode[$row['parent_code']])) {
                        $parent = $this->existingAccount($tenantId, $row['parent_code']);
                        if ($parent === null) {
                            throw new RuntimeException("Parent [{$row['parent_code']}] not seeded yet for [{$row['code']}].");
                        }
                        $byCode[$row['parent_code']] = (int) $parent['row_id'];
                    }
                    $parentRowId = $byCode[$row['parent_code']];
                }

                try {
                    $account = Account::query()->create([
                        'code' => $row['code'],
                        'name' => trim($row['name']),
                        'account_type' => $this->mapAccountType($row['code']),
                        'normal_balance' => $row['normal'],
                        'level' => $row['level'],
                        'is_postable' => $row['level'] === 4,
                        'is_active' => true,
                        'parent_row_id' => $parentRowId,
                        'legacy_parent_code' => $row['parent_code'],
                    ]);
                } catch (\Throwable $e) {
                    throw new RuntimeException("Failed to insert [{$row['code']}]: {$e->getMessage()}", previous: $e);
                }

                $byCode[$row['code']] = (int) $account->row_id;
                $inserted++;
            }
        });

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'settings_seeded' => $seedSettings ? $this->seedDefaultSettings($tenantId) : 0,
        ];
    }

    /** @return array{would_insert: int, would_skip: int} */
    public function preview(): array
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required.');
        }

        $tenantId = $this->context->id();
        $wouldInsert = 0;
        $wouldSkip = 0;
        foreach ($this->template()->rows() as $row) {
            if ($this->existingAccount($tenantId, $row['code']) !== null) {
                $wouldSkip++;
            } else {
                $wouldInsert++;
            }
        }

        return ['would_insert' => $wouldInsert, 'would_skip' => $wouldSkip];
    }

    public function reset(): int
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required.');
        }

        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');

        return DB::connection($connectionName)
            ->table('accounts')
            ->where('tenant_id', $this->context->id())
            ->delete();
    }

    private function existingAccount(int $tenantId, string $code): ?array
    {
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $row = DB::connection($connectionName)->table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first(['row_id']);

        return $row === null ? null : (array) $row;
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

    private function template(): CoaTemplate
    {
        return match ($this->context->tenant()->coa_variant ?? 'standard') {
            'trading' => $this->tradingTemplate,
            'cooperative' => $this->cooperativeTemplate,
            default => $this->standardTemplate,
        };
    }

    private function seedDefaultSettings(int $tenantId): int
    {
        if (($this->context->tenant()->coa_variant ?? 'standard') !== 'standard') {
            return 0;
        }

        $defaults = [
            'account.pencairan_spp' => '1.1.03.01',
            'account.pencairan_uep' => '1.1.03.02',
            'account.pencairan_pl' => '1.1.03.03',
        ];

        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now();
        $seeded = 0;

        foreach ($defaults as $key => $value) {
            $existing = DB::connection($connectionName)->table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->exists();

            if ($existing) {
                continue;
            }

            DB::connection($connectionName)->table('tenant_settings')->insert([
                'tenant_id' => $tenantId,
                'key' => $key,
                'value' => $value,
                'value_type' => 'string',
                'is_encrypted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $seeded++;
        }

        return $seeded;
    }
}
