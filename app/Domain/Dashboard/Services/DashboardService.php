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
     *     recent_journals: list<array{row_id:int,journal_number:?string,transaction_date:string,description:?string,amount:float,source_type:?string}>,
     *     counts: array{members:int,groups:int}
     * }
     */
    public function build(): array
    {
        $today = CarbonImmutable::today();
        $profile = OrganizationProfile::query()->first(['short_name', 'legal_name']);

        $cashBalance = $this->cashBalance();

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
            ],
            'recent_journals' => $this->recentJournals(),
            'counts' => [
                'members' => 0,
                'groups' => 0,
            ],
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
