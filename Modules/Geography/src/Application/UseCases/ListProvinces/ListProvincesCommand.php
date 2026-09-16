<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListProvinces;

final readonly class ListProvincesCommand
{
    public function __construct(public array $filters)
    {
    }
}
