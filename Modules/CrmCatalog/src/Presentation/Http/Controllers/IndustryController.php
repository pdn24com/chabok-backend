<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmCatalog\Application\UseCases\ListIndustries\ListIndustriesHandler;
use Modules\CrmCatalog\Presentation\Http\Requests\ListIndustriesRequest;
use Modules\CrmCatalog\Presentation\Http\Resources\IndustryResource;
use Modules\CrmCatalog\Presentation\Mappers\IndustryCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

final class IndustryController
{
    public function index(ListIndustriesRequest $request, ListIndustriesHandler $handler): JsonResponse
    {
        $result = $handler->handle(IndustryCommandMapper::listing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::paginated($request, $result->industries, fn ($industry): array => (new IndustryResource($industry))->resolve($request));
    }
}
