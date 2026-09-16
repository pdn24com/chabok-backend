<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Geography\Presentation\Http\Requests\ListCitiesRequest;
use Modules\Geography\Presentation\Http\Requests\ListProvincesRequest;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Geography\Application\Data\GeographyData;
use Modules\Geography\Presentation\Mappers\GeographyCommandMapper;
use Modules\Geography\Application\UseCases\ListProvinces\ListProvincesHandler;
use Modules\Geography\Application\UseCases\ListCities\ListCitiesHandler;
use Modules\Geography\Application\UseCases\GetCity\GetCityHandler;

final readonly class GeographyController
{
    public function __construct(
        private ListProvincesHandler $provincesHandler,
        private ListCitiesHandler $citiesHandler,
        private GetCityHandler $cityHandler,
    )
    {
    }

    public function provinces(ListProvincesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::paginated($request, $this->provincesHandler->handle(GeographyCommandMapper::provinces($filters))->data, fn($row): array => GeographyData::provinceResource((array) $row));
    }

    public function cities(ListCitiesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::paginated($request, $this->citiesHandler->handle(GeographyCommandMapper::cities($filters))->data, fn($row): array => GeographyData::cityResource((array) $row));
    }

    public function city(Request $request, string $cityId): JsonResponse
    {
        return ApiResponder::success($request, $this->cityHandler->handle(GeographyCommandMapper::city($cityId))->data);
    }
}
