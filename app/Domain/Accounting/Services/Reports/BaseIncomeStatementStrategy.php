<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Models\Tenant\OrganizationProfile;
use Carbon\CarbonImmutable;

abstract class BaseIncomeStatementStrategy
{
    protected IncomeStatementData $data;

    private static string $variant = 'standard';

    public static function setVariant(string $variant): void
    {
        self::$variant = $variant;
    }

    public function __construct(
        private readonly AccountBalanceQuery $balances,
        IncomeStatementData $data,
    ) {
        $this->data = $data;
    }

    public function build(int $year, ?int $month): array
    {
        $period = $this->data->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $periodFrom = CarbonImmutable::parse($period['from'])->startOfDay();
        $periodUntil = CarbonImmutable::parse($period['until_exclusive'])->startOfDay();
        $priorUntil = $period['is_monthly'] ? $periodFrom : $yearStart;

        $openings = $this->balances->openings($year);
        $ytdMovements = $this->balances->movements($yearStart, $asOf->addDay()->startOfDay());
        $priorMovements = $period['is_monthly']
            ? $this->balances->movements($yearStart, $priorUntil)
            : collect();
        $periodMovements = $this->balances->movements($periodFrom, $periodUntil);
        $accounts = $this->data->accounts($periodFrom, $periodUntil)
            ->keyBy(fn (Account $account): int => (int) $account->row_id);

        $rows = [];
        foreach ($accounts as $account) {
            $ytd = $this->data->cumulativeSigned($account, $openings, $ytdMovements);
            $prior = $period['is_monthly']
                ? $this->data->cumulativeSigned($account, $openings, $priorMovements)
                : 0.0;
            $current = $this->data->periodSigned($account, $periodMovements);

            $rows[] = [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'account_type' => (string) $account->account_type,
                'level' => (int) $account->level,
                'prior' => round($prior, 2),
                'current' => round($current, 2),
                'ytd' => round($ytd, 2),
            ];
        }

        $profile = OrganizationProfile::query()->first();

        return [
            'title' => $this->title(),
            'coa_variant' => self::$variant,
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? ''),
                'short_name' => $profile?->short_name,
            ],
            'header_lalu' => $period['is_monthly'] ? 'Bulan Lalu' : 'Tahun Lalu',
            'header_sekarang' => $period['is_monthly'] ? 'Bulan Ini' : 'Tahun Ini',
            'rows' => $rows,
            'groups' => $this->sections($rows),
            'sections' => $this->sections($rows),
            'summary' => $this->summary($rows),
        ];
    }

    abstract public function title(): string;

    abstract protected function sections(array $rows): array;

    abstract protected function summary(array $rows): array;

    protected function value(array $rows, string $code, string $field): float
    {
        return (float) ($rows[$code][$field] ?? 0.0);
    }

    protected function total(array $rows, string $field): float
    {
        return round(array_sum(array_column($rows, $field)), 2);
    }
}
