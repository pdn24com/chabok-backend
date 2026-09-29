<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmCatalog\Application\UseCases\ListCatalogCategories\ListCatalogCategoriesHandler;
use Modules\CrmCatalog\Application\UseCases\ListCatalogPersonas\ListCatalogPersonasHandler;
use Modules\CrmCatalog\Application\UseCases\ListCatalogSalesModels\ListCatalogSalesModelsHandler;
use Modules\CrmCatalog\Presentation\Http\Requests\ListCatalogReferenceRequest;
use Modules\CrmCatalog\Presentation\Http\Resources\CatalogCategoryResource;
use Modules\CrmCatalog\Presentation\Http\Resources\CatalogPersonaResource;
use Modules\CrmCatalog\Presentation\Http\Resources\CatalogSalesModelResource;
use Modules\CrmCatalog\Presentation\Mappers\CatalogCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The reference lists the catalog item form fills its selects from. */
final class CatalogReferenceController
{
    public function categories(ListCatalogReferenceRequest $request, ListCatalogCategoriesHandler $handler): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::categories($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, $result->categories
            ->map(fn ($category): array => (new CatalogCategoryResource($category))->resolve($request))
            ->all());
    }

    public function personas(ListCatalogReferenceRequest $request, ListCatalogPersonasHandler $handler): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::personas($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, $result->personas
            ->map(fn ($persona): array => (new CatalogPersonaResource($persona))->resolve($request))
            ->all());
    }

    public function salesModels(ListCatalogReferenceRequest $request, ListCatalogSalesModelsHandler $handler): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::salesModels($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, $result->salesModels
            ->map(fn ($salesModel): array => (new CatalogSalesModelResource($salesModel))->resolve($request))
            ->all());
    }
}
