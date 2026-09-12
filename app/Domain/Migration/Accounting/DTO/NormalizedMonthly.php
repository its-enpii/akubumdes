<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting\DTO;

final readonly class NormalizedMonthly
{
    public function __construct(
        public string $accountCode,
        public int $accountRowId,
        public int $fiscalYear,
        public int $fiscalMonth,
        public string $debit,
        public string $credit,
        public string $sourceId,
    ) {}
}
