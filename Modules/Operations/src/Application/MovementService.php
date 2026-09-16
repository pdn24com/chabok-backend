<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class MovementService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteHandler $planConsignmentRoute,
        private \Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteHandler $clusterConsignmentRoute,
        private \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler $getRoutePlan,
        private \Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansHandler $listRoutePlans,
    )
    {
    }

    public function plan(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $correlationId): array
    {
        return $this->planConsignmentRoute->handle(new \Modules\Operations\Application\UseCases\PlanConsignmentRoute\PlanConsignmentRouteCommand($actor, $nodeId, $consignmentId, $correlationId))->data;
    }

    public function cluster(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        int $expectedPlanVersion,
        string $correlationId,
    ): array
    {
        return $this->clusterConsignmentRoute->handle(new \Modules\Operations\Application\UseCases\ClusterConsignmentRoute\ClusterConsignmentRouteCommand($actor, $nodeId, $consignmentId, $expectedPlanVersion, $correlationId))->data;
    }

    public function routePlan(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->getRoutePlan->handle(new \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand($actor, $nodeId, $id))->data;
    }

    public function listRoutePlans(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->listRoutePlans->handle(new \Modules\Operations\Application\UseCases\ListRoutePlans\ListRoutePlansCommand($actor, $nodeId))->data;
    }
}
