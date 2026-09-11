<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

final class CooperativeIncomeStatementStrategy extends BaseIncomeStatementStrategy
{
    public function title(): string
    {
        return 'Perhitungan Hasil Usaha';
    }

    protected function sections(array $rows): array
    {
        $grouped = $this->buckets($rows);

        return [
            [
                'label' => 'Pendapatan',
                'type' => 'revenue',
                'rows' => [
                    ...$grouped['revenue_ops'],
                    ...$grouped['revenue_non'],
                ],
                'total' => $this->total([...$grouped['revenue_ops'], ...$grouped['revenue_non']], 'ytd'),
                'current' => $this->total([...$grouped['revenue_ops'], ...$grouped['revenue_non']], 'current'),
                'prior' => $this->total([...$grouped['revenue_ops'], ...$grouped['revenue_non']], 'prior'),
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
            [
                'label' => 'Pajak',
                'type' => 'expense',
                'rows' => $grouped['tax'],
                'total' => $this->total($grouped['tax'], 'ytd'),
                'current' => 0.0,
                'prior' => 0.0,
            ],
        ];
    }

    protected function summary(array $rows): array
    {
        $grouped = $this->buckets($rows);
        $revenue = $this->total([...$grouped['revenue_ops'], ...$grouped['revenue_non']], 'ytd');
        $expense = $this->total([...$grouped['expense_ops'], ...$grouped['expense_non']], 'ytd');
        $tax = $this->total($grouped['tax'], 'ytd');
        $phu = round($revenue - $expense, 2);

        return [
            'operating' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $phu],
            'non_operating' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => round(-$tax, 2)],
            'before_tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $phu],
            'tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $tax],
            'after_tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => round($phu - $tax, 2)],
        ];
    }

    private function buckets(array $rows): array
    {
        $buckets = new IncomeStatementData;
        $grouped = ['revenue_ops' => [], 'revenue_non' => [], 'expense_ops' => [], 'expense_non' => [], 'tax' => []];

        foreach ($rows as $row) {
            $grouped[$buckets->bucket((string) $row['code'], (string) $row['account_type'])][] = $row;
        }

        return $grouped;
    }
}
