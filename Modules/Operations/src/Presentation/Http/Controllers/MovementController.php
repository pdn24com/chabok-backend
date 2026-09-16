<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class MovementController
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansHandler $listRoutePlans,
        private \Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteHandler $plan,
        private \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler $routePlan,
        private \Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteHandler $cluster,
    )
    {
    }

    public function plans(Request $r): JsonResponse
    {
        return ApiResponder::success($r, $this->listRoutePlans->handle(new \Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansCommand($r->attributes->get('principal'), $this->node($r)))->data);
    }

    public function plan(Request $r, string $consignmentId): JsonResponse
    {
        StrictPayload::assertOnly($r, []);
        return ApiResponder::success($r, $this->plan->handle(new \Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteCommand($r->attributes->get('principal'), $this->node($r), $consignmentId, $this->correlation($r)))->data, status: 201);
    }

    public function showPlan(Request $r, string $id): JsonResponse
    {
        return ApiResponder::success($r, $this->routePlan->handle(new \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand($r->attributes->get('principal'), $this->node($r), $id))->data);
    }

    public function cluster(\Modules\Operations\Presentation\Http\Requests\ClusterRouteRequest $r, string $consignmentId): JsonResponse
    {
        $in = $r->validated();
        return ApiResponder::success($r, $this->cluster->handle(new \Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteCommand($r->attributes->get('principal'), $this->node($r), $consignmentId, (int) $in['expected_route_plan_version'], $this->correlation($r)))->data);
    }

    private function correlation(Request $r): string
    {
        return (string) $r->attributes->get('correlation_id');
    }

    private function node(Request $r): string
    {
        $id = $r->attributes->get('node_id');
        if (!is_string($id) || $id === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        }
        return $id;
    }
}
