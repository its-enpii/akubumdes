<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Tenancy\TenantContext;

final readonly class IncomeStatementService
{
    public function __construct(
        private AccountBalanceQuery $balances,
        private IncomeStatementData $data,
        private StandardIncomeStatementStrategy $standard,
        private TradingIncomeStatementStrategy $trading,
        private CooperativeIncomeStatementStrategy $cooperative,
        private TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        BaseIncomeStatementStrategy::setVariant($this->coaVariant());

        $strategy = $this->strategy();

        return $strategy->build($year, $month);
    }

    public function title(): string
    {
        return $this->strategy()->title();
    }

    public function coaVariant(): string
    {
        return (string) ($this->context->isInitialized() ? ($this->context->tenant()->coa_variant ?? 'standard') : 'standard');
    }

    private function strategy(): StandardIncomeStatementStrategy|TradingIncomeStatementStrategy|CooperativeIncomeStatementStrategy
    {
        $variant = $this->coaVariant();

        return match ($variant) {
            'trading' => $this->trading,
            'cooperative' => $this->cooperative,
            default => $this->standard,
        };
    }
}
