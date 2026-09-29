<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmOpportunitie\Application\UseCases\ListSalesFunnels\ListSalesFunnelsHandler;
use Modules\CrmOpportunitie\Presentation\Http\Requests\ListSalesFunnelsRequest;
use Modules\CrmOpportunitie\Presentation\Http\Resources\SalesFunnelResource;
use Modules\CrmOpportunitie\Presentation\Mappers\OpportunityCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The pipelines of the tenant and the steps the opportunity board draws its columns from. */
final class SalesFunnelController
{
    public function index(ListSalesFunnelsRequest $request, ListSalesFunnelsHandler $handler): JsonResponse
    {
        $result = $handler->handle(OpportunityCommandMapper::funnelListing($request->attributes->get('principal'), $request->validated()));

        // A tenant keeps a handful of funnels, so the set is returned whole rather than by the page.
        return ApiResponder::success($request, SalesFunnelResource::collection($result->funnels)->resolve($request));
    }
}
