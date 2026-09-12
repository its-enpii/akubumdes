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
            ->where(function ($q) use ($periodFrom): void {
                $q->whereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>=', $periodFrom->toDateString());
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
