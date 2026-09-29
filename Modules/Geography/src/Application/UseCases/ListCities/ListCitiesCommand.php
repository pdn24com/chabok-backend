<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCities;

use Modules\Geography\Application\Dto\GeographySearchDto;

final readonly class ListCitiesCommand
{
    public function __construct(public GeographySearchDto $filters) {}
}
