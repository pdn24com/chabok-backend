<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Geography\Application\UseCases\GetCity\GetCityHandler;
use Modules\Geography\Application\UseCases\GetCountry\GetCountryHandler;
use Modules\Geography\Application\UseCases\ListCities\ListCitiesHandler;
use Modules\Geography\Application\UseCases\ListCountries\ListCountriesHandler;
use Modules\Geography\Application\UseCases\ListProvinces\ListProvincesHandler;
use Modules\Geography\Presentation\Http\Requests\ListCitiesRequest;
use Modules\Geography\Presentation\Http\Requests\ListCountriesRequest;
use Modules\Geography\Presentation\Http\Requests\ListProvincesRequest;
use Modules\Geography\Presentation\Http\Resources\CityResource;
use Modules\Geography\Presentation\Http\Resources\CountryResource;
use Modules\Geography\Presentation\Http\Resources\ProvinceResource;
use Modules\Geography\Presentation\Mappers\GeographyCommandMapper;

final class GeographyController
{
    public function countries(ListCountriesRequest $request, ListCountriesHandler $listCountriesHandler): JsonResponse
    {
        return ApiResponder::paginated($request, $listCountriesHandler->handle(GeographyCommandMapper::countries($request->validated())), fn ($row): array => (new CountryResource($row))->resolve($request));
    }

    public function country(Request $request, GetCountryHandler $getCountryHandler, string $countryId): JsonResponse
    {
        return ApiResponder::success($request, new CountryResource($getCountryHandler->handle(GeographyCommandMapper::country($countryId))));
    }

    public function provinces(ListProvincesRequest $request, ListProvincesHandler $listProvincesHandler): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::paginated($request, $listProvincesHandler->handle(GeographyCommandMapper::provinces($filters)), fn ($row): array => (new ProvinceResource($row))->resolve($request));
    }

    public function cities(ListCitiesRequest $request, ListCitiesHandler $listCitiesHandler): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::paginated($request, $listCitiesHandler->handle(GeographyCommandMapper::cities($filters)), fn ($row): array => (new CityResource($row))->resolve($request));
    }

    public function city(Request $request, GetCityHandler $getCityHandler, string $cityId): JsonResponse
    {
        return ApiResponder::success($request, new CityResource($getCityHandler->handle(GeographyCommandMapper::city($cityId))));
    }
}
