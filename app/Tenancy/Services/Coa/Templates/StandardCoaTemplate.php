<?php

declare(strict_types=1);

namespace App\Tenancy\Services\Coa\Templates;

final class StandardCoaTemplate implements CoaTemplate
{
    /** @var list<array{code: string, name: string, normal: 'D'|'C', level: int, parent_code: ?string}> */
    private static array $rows = [];

    public function rows(): array
    {
        if (self::$rows === []) {
            self::$rows = require app_path('Tenancy/Services/Coa/Templates/datasets/standard.php');
        }

        return self::$rows;
    }
}
