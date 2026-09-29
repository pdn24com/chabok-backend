<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteCommand;
use Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteHandler;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler;
use Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansCommand;
use Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansHandler;
use Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteCommand;
use Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteHandler;
use Modules\Operations\Presentation\Http\Resources\RoutePlanResource;

/** Test fixture shorthand for focused use cases; no production facade. */
final readonly class MovementFixtures
{
    public function __construct(
        private PlanConsignmentRouteHandler $planConsignmentRoute,
        private ClusterConsignmentRouteHandler $clusterConsignmentRoute,
        private GetRoutePlanHandler $getRoutePlan,
        private ListRoutePlansHandler $listRoutePlans,
    ) {}

    public function plan(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        string $correlationId,
    ): array {
        return (new RoutePlanResource($this->planConsignmentRoute->handle(new PlanConsignmentRouteCommand($actor, $nodeId, $consignmentId, $correlationId))))->resolve();
    }

    public function cluster(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        int $expectedPlanVersion,
        string $correlationId,
    ): array {
        return (new RoutePlanResource($this->clusterConsignmentRoute->handle(new ClusterConsignmentRouteCommand($actor, $nodeId, $consignmentId, $expectedPlanVersion, $correlationId))))->resolve();
    }

    public function routePlan(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): array {
        return (new RoutePlanResource($this->getRoutePlan->handle(new GetRoutePlanCommand($actor, $nodeId, $id))))->resolve();
    }

    public function listRoutePlans(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return RoutePlanResource::collection($this->listRoutePlans->handle(new ListRoutePlansCommand($actor, $nodeId)))->resolve();
    }
}
