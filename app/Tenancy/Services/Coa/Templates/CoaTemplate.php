<?php

declare(strict_types=1);

namespace App\Tenancy\Services\Coa\Templates;

interface CoaTemplate
{
    /**
     * @return list<array{code: string, name: string, normal: 'D'|'C', level: int, parent_code: ?string}>
     */
    public function rows(): array;
}
