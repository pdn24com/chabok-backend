<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Mappers;

use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Application\UseCases\GetCity\GetCityCommand;
use Modules\Geography\Application\UseCases\GetCountry\GetCountryCommand;
use Modules\Geography\Application\UseCases\ListCities\ListCitiesCommand;
use Modules\Geography\Application\UseCases\ListCountries\ListCountriesCommand;
use Modules\Geography\Application\UseCases\ListProvinces\ListProvincesCommand;

final class GeographyCommandMapper
{
    public static function countries(array $filters): ListCountriesCommand
    {
        return new ListCountriesCommand(new GeographySearchDto(search: $filters['search'] ?? null, active: (bool) ($filters['active'] ?? true), page: (int) ($filters['page'] ?? 1), perPage: (int) ($filters['per_page'] ?? 250)));
    }

    public static function country(string $countryId): GetCountryCommand
    {
        return new GetCountryCommand($countryId);
    }

    public static function provinces(array $filters): ListProvincesCommand
    {
        return new ListProvincesCommand(new GeographySearchDto(search: $filters['search'] ?? null, active: (bool) ($filters['active'] ?? true), page: (int) ($filters['page'] ?? 1), perPage: (int) ($filters['per_page'] ?? 50)));
    }

    public static function cities(array $filters): ListCitiesCommand
    {
        return new ListCitiesCommand(new GeographySearchDto(search: $filters['search'] ?? null, active: (bool) ($filters['active'] ?? true), page: (int) ($filters['page'] ?? 1), perPage: (int) ($filters['per_page'] ?? 25), provinceId: $filters['province_id'] ?? null, provinceCode: $filters['province_code'] ?? null));
    }

    public static function city(string $cityId): GetCityCommand
    {
        return new GetCityCommand($cityId);
    }
}
