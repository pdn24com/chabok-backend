<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Mappers;

use Modules\Geography\Application\UseCases\ListProvinces\ListProvincesCommand;
use Modules\Geography\Application\UseCases\ListCities\ListCitiesCommand;
use Modules\Geography\Application\UseCases\GetCity\GetCityCommand;

final class GeographyCommandMapper
{
    public static function provinces(array $filters): ListProvincesCommand
    {
        return new ListProvincesCommand($filters);
    }

    public static function cities(array $filters): ListCitiesCommand
    {
        return new ListCitiesCommand($filters);
    }

    public static function city(string $cityId): GetCityCommand
    {
        return new GetCityCommand($cityId);
    }
}
