<?php

declare(strict_types=1);

namespace App\Tenancy\Services\Coa\Templates;

use App\Tenancy\Services\Coa\Templates\Support\CoaRows;

final class CooperativeCoaTemplate implements CoaTemplate
{
    public function rows(): array
    {
        return [
            ...CoaRows::ASSET_LIABILITY_AND_EQUITY,
            ...CoaRows::cooperativeIncome(),
            ...CoaRows::cooperativeExpenses(),
            ...CoaRows::cooperativeTax(),
        ];
    }
}
