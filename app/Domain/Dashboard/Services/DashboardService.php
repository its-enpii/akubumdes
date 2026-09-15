<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Models\Tenant\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan operasional tenant dari journal.
 */
final class DashboardService
{
    private const CASH_PREFIX = '1.1.01';

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    private function tenantId(): int
    {
        return $this->context->id();
    }

    /**
     * @return array{
     *     unit_name: ?string,
     *     as_of: string,
     *     cards: list<array{key:string,label:string,icon:string,value:float|int,format:string,hint:?string,tone:?string}>,
     *     trend: list<array{key:string,label:string,revenue:float,expense:float}>,
     *     top_revenues: list<array{account_code:string,account_name:string,amount:float}>,
     *     period: ?array{fiscal_year:int,fiscal_month:int,status:string},
     *     recent_journals: list<array{row_id:int,journal_number:?string,transaction_date:string,description:?string,amount:float,source_type:?string}>,
     * }
     */
    public function build(): array
    {
        $today = CarbonImmutable::today();
        $profile = OrganizationProfile::query()->first(['short_name', 'legal_name']);

        $cashBalance = $this->cashBalance();
        $assetsTotal = $this->rootBalance('1');
        $incomeTotals = $this->incomeTotals($today);
        $trend = $this->trend($today);
        $topRevenues = $this->topRevenues($today);
        $period = $this->period($today);
        $journalsMonth = $this->journalsMonth($today);

        $revenue = $incomeTotals['revenue'];
        $expense = $incomeTotals['expense'];
        $surplus = round($revenue - $expense, 2);
        $year = (int) $today->format('Y');
        $monthStart = $today->startOfMonth()->toDateString();
        $monthEnd = $today->endOfMonth()->toDateString();

        return [
            'unit_name' => $profile?->short_name ?: $profile?->legal_name,
            'as_of' => $today->toDateString(),
            'cards' => [
                [
                    'key' => 'cash',
                    'label' => 'Saldo Kas/Bank',
                    'icon' => 'account_balance',
                    'value' => $cashBalance,
                    'format' => 'money',
                    'hint' => 'Akun 1.1.01* (posted)',
                    'tone' => null,
                ],
                [
                    'key' => 'revenue_ytd',
                    'label' => 'Pendapatan YTD',
                    'icon' => 'trending_up',
                    'value' => $revenue,
                    'format' => 'money',
                    'hint' => sprintf('Tahun %d, posted', $year),
                    'tone' => null,
                ],
                [
                    'key' => 'expense_ytd',
                    'label' => 'Beban YTD',
                    'icon' => 'payments',
                    'value' => $expense,
                    'format' => 'money',
                    'hint' => sprintf('Tahun %d, posted', $year),
                    'tone' => null,
                ],
                [
                    'key' => 'surplus_ytd',
                    'label' => 'Surplus/(Defisit) YTD',
                    'icon' => 'balance',
                    'value' => $surplus,
                    'format' => 'money',
                    'hint' => sprintf('Tahun %d, posted', $year),
                    'tone' => $surplus < 0 ? 'error' : null,
                ],
                [
                    'key' => 'assets_total',
                    'label' => 'Total Aset',
                    'icon' => 'account_balance_wallet',
                    'value' => $assetsTotal,
                    'format' => 'money',
                    'hint' => sprintf('Tahun %d, posted + opening', $year),
                    'tone' => null,
                ],
                [
                    'key' => 'journals_month',
                    'label' => 'Jurnal Posted Bulan Ini',
                    'icon' => 'receipt_long',
                    'value' => $journalsMonth,
                    'format' => 'count',
                    'hint' => sprintf('%s, posted', $today->translatedFormat('F Y')),
                    'tone' => null,
                ],
            ],
            'trend' => $trend,
            'top_revenues' => $topRevenues,
            'period' => $period,
            'recent_journals' => $this->recentJournals(),
        ];
    }

    private function cashBalance(): float
    {
        $tenantId = $this->tenantId();

        $cashIds = DB::connection('tenant')
            ->table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', self::CASH_PREFIX.'%')
            ->pluck('row_id');

        if ($cashIds->isEmpty()) {
            return 0.0;
        }

        $year = (int) date('Y');

        $opening = (float) DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->whereIn('account_row_id', $cashIds)
            ->where('fiscal_year', $year)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS bal')
            ->value('bal');

        $yearStart = sprintf('%04d-01-01', $year);

        $movement = (float) DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $yearStart)
            ->whereIn('lines.account_row_id', $cashIds)
            ->selectRaw('COALESCE(SUM(lines.debit - lines.credit), 0) AS bal')
            ->value('bal');

        return round($opening + $movement, 2);
    }

    /**
     * @return array{revenue:float,expense:float}
     */
    private function incomeTotals(CarbonImmutable $today): array
    {
        $tenantId = $this->tenantId();
        $yearStart = $today->startOfYear()->toDateString();

        $rows = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts as accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.tenant_id', $tenantId)
            ->where('accounts.tenant_id', $tenantId)
            ->where('accounts.is_active', true)
            ->where('accounts.is_postable', true)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $yearStart)
            ->where(function ($query): void {
                self::whereAccountRoot($query, '4');
                self::whereAccountRoot($query, '5', 'or');
            })
            ->selectRaw('
                COALESCE(SUM(CASE WHEN SUBSTR(accounts.code, 1, 1) = \'4\' THEN lines.credit - lines.debit ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN SUBSTR(accounts.code, 1, 1) = \'5\' THEN lines.debit - lines.credit ELSE 0 END), 0) AS expense
            ')
            ->first();

        return [
            'revenue' => round((float) ($rows?->revenue ?? 0), 2),
            'expense' => round((float) ($rows?->expense ?? 0), 2),
        ];
    }

    private function rootBalance(string $root): float
    {
        $tenantId = $this->tenantId();

        $accountIds = DB::connection('tenant')
            ->table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where(function ($query) use ($root): void {
                self::whereAccountRoot($query, $root);
            })
            ->pluck('row_id');

        if ($accountIds->isEmpty()) {
            return 0.0;
        }

        $year = (int) CarbonImmutable::today()->format('Y');

        $opening = (float) DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->whereIn('account_row_id', $accountIds)
            ->where('fiscal_year', $year)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS bal')
            ->value('bal');

        $yearStart = sprintf('%04d-01-01', $year);

        $movement = (float) DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $yearStart)
            ->whereIn('lines.account_row_id', $accountIds)
            ->selectRaw('COALESCE(SUM(lines.debit - lines.credit), 0) AS bal')
            ->value('bal');

        return round($opening + $movement, 2);
    }

    /**
     * @return list<array{key:string,label:string,revenue:float,expense:float}>
     */
    private function trend(CarbonImmutable $today): array
    {
        $tenantId = $this->tenantId();
        $months = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $today->subMonths($offset)->startOfMonth();
            $months[$month->format('Y-m')] = [
                'key' => $month->format('Y-m'),
                'label' => $month->shortLocaleMonth,
                'revenue' => 0.0,
                'expense' => 0.0,
            ];
        }

        $start = array_key_first($months).'-01';
        $end = $today->toDateString();

        $rows = DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts as accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.tenant_id', $tenantId)
            ->where('accounts.tenant_id', $tenantId)
            ->where('accounts.is_active', true)
            ->where('accounts.is_postable', true)
            ->where('entries.status', 'posted')
            ->whereBetween('entries.transaction_date', [$start, $end])
            ->where(function ($query): void {
                self::whereAccountRoot($query, '4');
                self::whereAccountRoot($query, '5', 'or');
            })
            ->groupBy('month')
            ->selectRaw('
                SUBSTR(entries.transaction_date, 1, 7) AS month,
                COALESCE(SUM(CASE WHEN SUBSTR(accounts.code, 1, 1) = \'4\' THEN lines.credit - lines.debit ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN SUBSTR(accounts.code, 1, 1) = \'5\' THEN lines.debit - lines.credit ELSE 0 END), 0) AS expense
            ')
            ->get();

        foreach ($rows as $row) {
            if (! array_key_exists($row->month, $months)) {
                continue;
            }

            $months[$row->month]['revenue'] = round((float) $row->revenue, 2);
            $months[$row->month]['expense'] = round((float) $row->expense, 2);
        }

        return array_values($months);
    }

    /**
     * @return list<array{account_code:string,account_name:string,amount:float}>
     */
    private function topRevenues(CarbonImmutable $today): array
    {
        $tenantId = $this->tenantId();
        $yearStart = $today->startOfYear()->toDateString();

        return DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->join('accounts as accounts', function ($join): void {
                $join->on('accounts.tenant_id', '=', 'lines.tenant_id')
                    ->on('accounts.row_id', '=', 'lines.account_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.tenant_id', $tenantId)
            ->where('accounts.tenant_id', $tenantId)
            ->where('accounts.is_active', true)
            ->where('accounts.is_postable', true)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $yearStart)
            ->where(function ($query): void {
                self::whereAccountRoot($query, '4');
            })
            ->groupBy('accounts.row_id', 'accounts.code', 'accounts.name')
            ->havingRaw('SUM(lines.credit - lines.debit) > 0')
            ->orderByRaw('SUM(lines.credit - lines.debit) DESC')
            ->limit(5)
            ->get([
                'accounts.code as account_code',
                'accounts.name as account_name',
                DB::raw('SUM(lines.credit - lines.debit) as amount'),
            ])
            ->map(fn ($row): array => [
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    private static function whereAccountRoot($query, string $root, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}(function ($query) use ($root): void {
            $query->where(function ($query) use ($root): void {
                $query->where('level', 1)
                    ->where('code', 'like', $root.'%');
            })->orWhere(function ($query) use ($root): void {
                $query->where('level', '>', 1)
                    ->where('code', 'like', $root.'.%');
            });
        });
    }

    /**
     * @return ?array{fiscal_year:int,fiscal_month:int,status:string}
     */
    private function period(CarbonImmutable $today): ?array
    {
        $period = DB::connection('tenant')
            ->table('fiscal_periods')
            ->where('tenant_id', $this->tenantId())
            ->where('status', 'open')
            ->where('starts_at', '<=', $today->toDateString())
            ->where('ends_at', '>=', $today->toDateString())
            ->orderByDesc('starts_at')
            ->first(['fiscal_year', 'fiscal_month', 'status']);

        if ($period === null) {
            $period = DB::connection('tenant')
                ->table('fiscal_periods')
                ->where('tenant_id', $this->tenantId())
                ->where('status', 'open')
                ->orderByDesc('starts_at')
                ->first(['fiscal_year', 'fiscal_month', 'status']);
        }

        if ($period === null) {
            return null;
        }

        return [
            'fiscal_year' => (int) $period->fiscal_year,
            'fiscal_month' => (int) $period->fiscal_month,
            'status' => (string) $period->status,
        ];
    }

    private function journalsMonth(CarbonImmutable $today): int
    {
        $monthStart = $today->startOfMonth()->toDateString();
        $monthEnd = $today->endOfMonth()->toDateString();

        return (int) DB::connection('tenant')
            ->table('journal_entries')
            ->where('tenant_id', $this->tenantId())
            ->where('status', 'posted')
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->count();
    }

    /**
     * @return list<array{row_id:int,journal_number:?string,transaction_date:string,description:?string,amount:float,source_type:?string}>
     */
    private function recentJournals(): array
    {
        $entries = JournalEntry::query()
            ->where('status', 'posted')
            ->orderByDesc('transaction_date')
            ->orderByDesc('row_id')
            ->limit(20)
            ->get(['row_id', 'journal_number', 'transaction_date', 'description', 'source_type']);

        if ($entries->isEmpty()) {
            return [];
        }

        $ids = $entries->pluck('row_id')->all();
        $totals = DB::connection('tenant')
            ->table('journal_lines')
            ->where('tenant_id', $this->tenantId())
            ->whereIn('journal_entry_row_id', $ids)
            ->selectRaw('journal_entry_row_id')
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total')
            ->groupBy('journal_entry_row_id')
            ->get()
            ->keyBy('journal_entry_row_id');

        return $entries->map(function (JournalEntry $entry) use ($totals): array {
            return [
                'row_id' => (int) $entry->row_id,
                'journal_number' => $entry->journal_number,
                'transaction_date' => $entry->transaction_date?->toDateString() ?? '',
                'description' => $entry->description,
                'amount' => round((float) ($totals->get($entry->row_id)?->debit_total ?? 0), 2),
                'source_type' => $entry->source_type,
            ];
        })->all();
    }
}
