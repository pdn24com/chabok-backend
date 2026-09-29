<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteCommand;
use Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteHandler;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler;
use Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansCommand;
use Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansHandler;
use Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteCommand;
use Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteHandler;
use Modules\Operations\Presentation\Http\Requests\ClusterRouteRequest;
use Modules\Operations\Presentation\Http\Resources\RoutePlanResource;

final class MovementController
{
    public function plans(Request $r, ListRoutePlansHandler $listRoutePlansHandler): JsonResponse
    {
        return ApiResponder::success($r, RoutePlanResource::collection($listRoutePlansHandler->handle(new ListRoutePlansCommand($r->attributes->get('principal'), $this->node($r))))->resolve($r));
    }

    public function plan(Request $r, PlanConsignmentRouteHandler $planConsignmentRouteHandler, string $consignmentId): JsonResponse
    {
        StrictPayload::assertOnly($r, []);

        return ApiResponder::success($r, (new RoutePlanResource($planConsignmentRouteHandler->handle(new PlanConsignmentRouteCommand($r->attributes->get('principal'), $this->node($r), $consignmentId, (string) $r->attributes->get('correlation_id')))))->resolve($r), status: 201);
    }

    public function showPlan(Request $r, GetRoutePlanHandler $getRoutePlanHandler, string $id): JsonResponse
    {
        return ApiResponder::success($r, (new RoutePlanResource($getRoutePlanHandler->handle(new GetRoutePlanCommand($r->attributes->get('principal'), $this->node($r), $id))))->resolve($r));
    }

    public function cluster(ClusterRouteRequest $r, ClusterConsignmentRouteHandler $clusterConsignmentRouteHandler, string $consignmentId): JsonResponse
    {
        $in = $r->validated();

        return ApiResponder::success($r, (new RoutePlanResource($clusterConsignmentRouteHandler->handle(new ClusterConsignmentRouteCommand($r->attributes->get('principal'), $this->node($r), $consignmentId, (int) $in['expected_route_plan_version'], (string) $r->attributes->get('correlation_id')))))->resolve($r));
    }

    private function node(Request $r): string
    {
        $id = $r->attributes->get('node_id');
        if (! is_string($id) || $id === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required');
        }

        return $id;
    }
}
