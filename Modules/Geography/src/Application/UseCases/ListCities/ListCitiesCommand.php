<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCities;

final readonly class ListCitiesCommand
{
    public function __construct(public array $filters)
    {
    }
}
