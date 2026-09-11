<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

final class StandardIncomeStatementStrategy extends BaseIncomeStatementStrategy
{
    public function title(): string
    {
        return 'Laporan Laba Rugi';
    }

    protected function sections(array $rows): array
    {
        $grouped = $this->groupLevel2($rows);

        return [
            [
                'label' => 'Pendapatan',
                'type' => 'revenue',
                'rows' => $grouped['revenue_ops'],
                'total' => $this->total($grouped['revenue_ops'], 'ytd'),
                'current' => $this->total($grouped['revenue_ops'], 'current'),
                'prior' => $this->total($grouped['revenue_ops'], 'prior'),
            ],
            [
                'label' => 'Beban',
                'type' => 'expense',
                'rows' => [
                    ...$grouped['expense_ops'],
                    ...$grouped['expense_non'],
                ],
                'total' => $this->total([...$grouped['expense_ops'], ...$grouped['expense_non']], 'ytd'),
                'current' => $this->total([...$grouped['expense_ops'], ...$grouped['expense_non']], 'current'),
                'prior' => $this->total([...$grouped['expense_ops'], ...$grouped['expense_non']], 'prior'),
            ],
        ];
    }

    protected function summary(array $rows): array
    {
        $grouped = $this->groupLevel2($rows);
        $revenue = $this->total($grouped['revenue_ops'], 'ytd');
        $expense = $this->total([...$grouped['expense_ops'], ...$grouped['expense_non']], 'ytd');
        $tax = 0.0;
        $afterTax = round($revenue - $expense - $tax, 2);

        return [
            'operating' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => round($revenue - $expense, 2)],
            'non_operating' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => round(-$tax, 2)],
            'before_tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => round($revenue - $expense, 2)],
            'tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $tax],
            'after_tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $afterTax],
        ];
    }

    private function groupLevel2(array $rows): array
    {
        $groups = [
            'revenue_ops' => [],
            'revenue_non' => [],
            'expense_ops' => [],
            'expense_non' => [],
            'tax' => [],
        ];

        foreach ($rows as $row) {
            $bucketName = $this->data->bucket(
                (string) $row['code'],
                (string) $row['account_type'],
            );
            $groups[$bucketName][] = $row;
        }

        return $groups;
    }
}
