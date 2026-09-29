<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCountries;

use Modules\Geography\Application\Dto\GeographySearchDto;

final readonly class ListCountriesCommand
{
    public function __construct(public GeographySearchDto $filters) {}
}
