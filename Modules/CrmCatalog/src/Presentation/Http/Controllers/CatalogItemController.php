<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmCatalog\Application\UseCases\CreateCatalogItem\CreateCatalogItemHandler;
use Modules\CrmCatalog\Application\UseCases\GetCatalogItem\GetCatalogItemHandler;
use Modules\CrmCatalog\Application\UseCases\ListCatalogItems\ListCatalogItemsHandler;
use Modules\CrmCatalog\Application\UseCases\UpdateCatalogItem\UpdateCatalogItemHandler;
use Modules\CrmCatalog\Presentation\Http\Requests\CreateCatalogItemRequest;
use Modules\CrmCatalog\Presentation\Http\Requests\ListCatalogItemsRequest;
use Modules\CrmCatalog\Presentation\Http\Requests\UpdateCatalogItemRequest;
use Modules\CrmCatalog\Presentation\Http\Resources\CatalogItemListResource;
use Modules\CrmCatalog\Presentation\Http\Resources\CatalogItemResource;
use Modules\CrmCatalog\Presentation\Mappers\CatalogCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The catalog of what the tenant sells: the table and the identity card of each item. */
final class CatalogItemController
{
    public function index(ListCatalogItemsRequest $request, ListCatalogItemsHandler $handler): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::listing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, $result->items
            ->map(fn ($item): array => (new CatalogItemListResource($item))->resolve($request))
            ->all());
    }

    public function show(Request $request, GetCatalogItemHandler $handler, string $catalogItemId): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::item($request->attributes->get('principal'), $catalogItemId));

        return ApiResponder::success($request, new CatalogItemResource($result->item));
    }

    public function store(CreateCatalogItemRequest $request, CreateCatalogItemHandler $handler): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::draft($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, new CatalogItemResource($result->item), status: 201);
    }

    public function update(UpdateCatalogItemRequest $request, UpdateCatalogItemHandler $handler, string $catalogItemId): JsonResponse
    {
        $result = $handler->handle(CatalogCommandMapper::changes($request->attributes->get('principal'), $catalogItemId, $request->validated()));

        return ApiResponder::success($request, new CatalogItemResource($result->item));
    }
}
