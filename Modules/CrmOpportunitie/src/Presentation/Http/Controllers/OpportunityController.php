<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmOpportunitie\Application\UseCases\CreateOpportunity\CreateOpportunityHandler;
use Modules\CrmOpportunitie\Application\UseCases\ListOpportunityBoard\ListOpportunityBoardHandler;
use Modules\CrmOpportunitie\Application\UseCases\ListOpportunityEvents\ListOpportunityEventsHandler;
use Modules\CrmOpportunitie\Application\UseCases\TransitionOpportunityStep\TransitionOpportunityStepHandler;
use Modules\CrmOpportunitie\Presentation\Http\Requests\CreateOpportunityRequest;
use Modules\CrmOpportunitie\Presentation\Http\Requests\CreateStepTransitionRequest;
use Modules\CrmOpportunitie\Presentation\Http\Requests\ListOpportunityBoardRequest;
use Modules\CrmOpportunitie\Presentation\Http\Resources\FunnelStepResource;
use Modules\CrmOpportunitie\Presentation\Http\Resources\OpportunityCardResource;
use Modules\CrmOpportunitie\Presentation\Http\Resources\OpportunityEventResource;
use Modules\CrmOpportunitie\Presentation\Http\Resources\OpportunityResource;
use Modules\CrmOpportunitie\Presentation\Http\Resources\SalesFunnelResource;
use Modules\CrmOpportunitie\Presentation\Mappers\OpportunityCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The opportunity board, the opening of an opportunity, its moves and the history they leave behind. */
final class OpportunityController
{
    public function index(ListOpportunityBoardRequest $request, ListOpportunityBoardHandler $handler): JsonResponse
    {
        $result = $handler->handle(OpportunityCommandMapper::board($request->attributes->get('principal'), $request->validated()));

        // The board answers as columns rather than a flat list: a step nobody has reached is still a
        // column, and a client that grouped the rows itself could not tell that it existed.
        $columns = [];
        foreach ($result->steps as $step) {
            $stepId = (string) $step->sales_funnel_step_id;
            $columns[] = [
                'step' => (new FunnelStepResource($step))->resolve($request),
                'items' => array_map(
                    fn ($opportunity): array => (new OpportunityCardResource($opportunity))->resolve($request),
                    $result->opportunitiesByStepId[$stepId] ?? [],
                ),
            ];
        }

        return ApiResponder::success($request, [
            'funnel' => (new SalesFunnelResource($result->funnel))->resolve($request),
            'columns' => $columns,
        ]);
    }

    public function store(CreateOpportunityRequest $request, CreateOpportunityHandler $handler): JsonResponse
    {
        $result = $handler->handle(OpportunityCommandMapper::draft($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, [
            'opportunity' => (new OpportunityResource($result->opportunity))->resolve($request),
            'event' => (new OpportunityEventResource($result->event))->resolve($request),
        ], status: 201);
    }

    public function transition(CreateStepTransitionRequest $request, TransitionOpportunityStepHandler $handler, string $opportunityId): JsonResponse
    {
        $result = $handler->handle(OpportunityCommandMapper::transition($request->attributes->get('principal'), $opportunityId, $request->validated()));

        return ApiResponder::success($request, [
            'opportunity' => (new OpportunityResource($result->opportunity))->resolve($request),
            'event' => (new OpportunityEventResource($result->event))->resolve($request),
        ], status: 201);
    }

    public function events(Request $request, ListOpportunityEventsHandler $handler, string $opportunityId): JsonResponse
    {
        $result = $handler->handle(OpportunityCommandMapper::events($request->attributes->get('principal'), $opportunityId));

        return ApiResponder::success($request, OpportunityEventResource::collection($result->events)->resolve($request));
    }
}
