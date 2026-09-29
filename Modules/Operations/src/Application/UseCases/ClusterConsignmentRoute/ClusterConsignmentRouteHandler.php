<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ClusterConsignmentRoute;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\MovementAccessGuardInterface;
use Modules\Operations\Application\Contracts\ParcelLifecycleServiceInterface;
use Modules\Operations\Application\Contracts\RoutePlanGuardInterface;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

final readonly class ClusterConsignmentRouteHandler
{
    public function __construct(
        private MovementAccessGuardInterface $movementAccessGuard,
        private ConnectionInterface $connection,
        private RoutePlanGuardInterface $routePlanGuard,
        private ParcelLifecycleServiceInterface $parcelLifecycleService,
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private ClockInterface $clock,
        private GetRoutePlanHandler $getRoutePlanHandler,
        private RoutePlanRepositoryInterface $routePlanRepository,
    ) {}

    public function handle(ClusterConsignmentRouteCommand $command): RoutePlanRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $consignmentId = $command->consignmentId;
        $expectedPlanVersion = $command->expectedPlanVersion;
        $correlationId = $command->correlationId;
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $planId = $this->connection->transaction(function () use ($actor, $nodeId, $consignmentId, $expectedPlanVersion, $correlationId): string {
            $plan = $this->routePlanRepository->lockOpenPlan($actor->hqId, $consignmentId);
            if ($plan === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.active_route_plan_is_required');
            }
            $this->routePlanGuard->version($plan, $expectedPlanVersion, 'Route Plan');
            $this->routePlanGuard->assertPlanConfigurationAvailable($plan);
            $leg = $plan->legs()->where(['hq_id' => $actor->hqId, 'origin_node_id' => $nodeId, 'status' => 'PENDING'])->lockForUpdate()->first();
            if ($leg === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.no_next_route_leg_is_available_node');
            }
            if ((int) $leg->leg_order > 1 && ! $plan->legs()->where(['leg_order' => $leg->leg_order - 1, 'status' => 'RECEIVED'])->exists()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.preceding_route_leg_has_not_been_received');
            }
            $this->parcelLifecycleService->transition($actor, $consignmentId, ConsignmentStatus::InboundReceived->value, ConsignmentStatus::Routed->value, 'ROUTE_CLUSTERED', $nodeId, CustodyType::Node->value, $nodeId, $correlationId, routePlanId: (string) $plan->route_plan_id, routePlanLegId: (string) $leg->route_plan_leg_id);
            $this->consignmentLedgerAccess->setActiveRoute((string) $actor->hqId, $consignmentId, (string) $plan->route_plan_id, (string) $leg->route_plan_leg_id);
            $leg->forceFill([
                'status' => 'ROUTED',
                'routed_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $plan->forceFill([
                'status' => 'IN_PROGRESS',
                'version' => $expectedPlanVersion + 1,
                'updated_at' => $this->clock->now(),
            ])->save();

            return $plan->route_plan_id;
        }, attempts: 3);

        return $this->getRoutePlanHandler->handle(new GetRoutePlanCommand($actor, $nodeId, $planId));
    }
}
