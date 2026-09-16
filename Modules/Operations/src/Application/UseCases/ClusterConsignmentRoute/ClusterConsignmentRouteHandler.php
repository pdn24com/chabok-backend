<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ClusterConsignmentRoute;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ClusterConsignmentRouteHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\MovementAccessGuard $movementAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\MovementRepository $plans,
        private \Modules\Operations\Application\Services\RoutePlanGuard $routePlanGuard,
        private \Modules\Operations\Application\ParcelLifecycleService $lifecycle,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler $getRoutePlan,
    )
    {
    }

    public function handle(ClusterConsignmentRouteCommand $command): ClusterConsignmentRouteResult
    {
        return new ClusterConsignmentRouteResult($this->execute($command->actor, $command->nodeId, $command->consignmentId, $command->expectedPlanVersion, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        int $expectedPlanVersion,
        string $correlationId,
    ): array
    {
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $expectedPlanVersion, $correlationId): void {
            $plan = $this->plans->lockActivePlan($actor->hqId, $consignmentId);
            if ($plan === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active Route Plan is required.');
            }
            $this->routePlanGuard->version($plan, $expectedPlanVersion, 'Route Plan');
            $this->routePlanGuard->assertPlanConfigurationAvailable($plan);
            $leg = $this->plans->lockPendingLeg($actor->hqId, $plan->route_plan_id, $nodeId);
            if ($leg === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'No next route leg is available at this node.');
            }
            if ((int) $leg->leg_order > 1 && !$this->plans->previousLegReceived($plan->route_plan_id, (int) $leg->leg_order - 1)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The preceding route leg has not been received.');
            }
            $this->lifecycle->transition($actor, $consignmentId, 'IR', 'ROU', 'ROUTE_CLUSTERED', $nodeId, 'NODE', $nodeId, $correlationId, routePlanId: (string) $plan->route_plan_id, routePlanLegId: (string) $leg->route_plan_leg_id);
            $this->ledger->setActiveRoute((string) $actor->hqId, $consignmentId, (string) $plan->route_plan_id, (string) $leg->route_plan_leg_id);
            $this->plans->updateLeg($leg->route_plan_leg_id, ['status' => 'ROUTED', 'routed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            $this->plans->updatePlan($plan->route_plan_id, ['status' => 'IN_PROGRESS', 'version' => $expectedPlanVersion + 1, 'updated_at' => $this->clock->now()]);
        });
        $planId = (string) $this->plans->activePlanId($actor->hqId, $consignmentId);
        return $this->getRoutePlan->handle(new \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand($actor, $nodeId, $planId))->data;
    }
}
