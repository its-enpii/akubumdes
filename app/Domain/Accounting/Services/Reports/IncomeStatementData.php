<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use Carbon\CarbonImmutable;

final class IncomeStatementData
{
    private ?AccountBalanceQuery $balances = null;

    public function __construct(?AccountBalanceQuery $balances = null)
    {
        $this->balances = $balances;
    }

    public function resolvePeriod(int $year, ?int $month): array
    {
        return $this->requireBalances()->resolvePeriod($year, $month);
    }

    public function accounts(CarbonImmutable $periodFrom, CarbonImmutable $periodUntil)
    {
        return Account::query()
            ->whereIn('account_type', ['revenue', 'expense'])
            ->where('is_postable', true)
            ->whereDate('created_at', '<=', $periodUntil->toDateString())
            ->where(function ($q) use ($periodFrom, $periodUntil): void {
                $q->whereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>=', $periodFrom->toDateString())
                    ->orWhereIn('row_id', Account::query()
                        ->from('accounts as future_accounts')
                        ->join('journal_lines as lines', 'lines.account_row_id', '=', 'future_accounts.row_id')
                        ->join('journal_entries as entries', function ($join): void {
                            $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                                ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
                        })
                        ->where('entries.status', 'posted')
                        ->where('entries.transaction_date', '>=', $periodFrom->toDateString())
                        ->where('entries.transaction_date', '<', $periodUntil->toDateString())
                        ->whereDate('future_accounts.created_at', '<=', $periodUntil->toDateString())
                        ->select('future_accounts.row_id'));
            })
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'parent_row_id']);
    }

    public function bucket(string $code, string $type): string
    {
        if (str_starts_with($code, '7.4') || str_starts_with($code, '5.4')) {
            return 'tax';
        }

        if ($type === 'revenue') {
            return str_starts_with($code, '4.1') ? 'revenue_ops' : 'revenue_non';
        }

        if (str_starts_with($code, '5.1') || str_starts_with($code, '5.2')) {
            return 'expense_ops';
        }

        return 'expense_non';
    }

    public function cumulativeSigned(Account $account, $openings, $movements): float
    {
        $balances = $this->requireBalances();
        $opening = $balances->movementPair($openings->get((int) $account->row_id));
        $movement = $balances->movementPair($movements->get((int) $account->row_id));

        return $balances->signedBalance(
            $account,
            $opening['debit'] + $movement['debit'],
            $opening['credit'] + $movement['credit'],
        );
    }

    public function periodSigned(Account $account, $periodMovements): float
    {
        $balances = $this->requireBalances();
        $periodPair = $balances->movementPair($periodMovements->get((int) $account->row_id));

        return $balances->signedBalance($account, $periodPair['debit'], $periodPair['credit']);
    }

    private function requireBalances(): AccountBalanceQuery
    {
        return $this->balances ?? app(AccountBalanceQuery::class);
    }
}
