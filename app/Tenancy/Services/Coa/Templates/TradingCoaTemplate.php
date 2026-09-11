<?php

declare(strict_types=1);

namespace App\Tenancy\Services\Coa\Templates;

use App\Tenancy\Services\Coa\Templates\Support\CoaRows;

final class TradingCoaTemplate implements CoaTemplate
{
    public function rows(): array
    {
        return [
            ...CoaRows::ASSET_LIABILITY_AND_EQUITY,
            ...CoaRows::TRADING_INCOME,
            ...CoaRows::tradingCostOfGoodsSold(),
            ...CoaRows::sharedExpenses(),
            ...CoaRows::tradingTax(),
        ];
    }
}
