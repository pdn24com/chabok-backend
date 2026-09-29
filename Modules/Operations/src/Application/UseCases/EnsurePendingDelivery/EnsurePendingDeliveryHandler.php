<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\EnsurePendingDelivery;

use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\CorrelationIdProviderInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryNodeCapabilitiesInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Contracts\LastMileResolverInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final readonly class EnsurePendingDeliveryHandler
{
    public function __construct(
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private DeliveryNodeCapabilitiesInterface $deliveryNodeCapabilities,
        private LastMileResolverInterface $lastMileResolver,
        private ClockInterface $clock,
        private DeliveryTaskRecorderInterface $deliveryTaskRecorder,
        private CorrelationIdProviderInterface $correlationIdProvider,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
        private RoutePlanRepositoryInterface $routePlanRepository,
    ) {}

    public function handle(EnsurePendingDeliveryCommand $command): string
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $consignmentId = $command->consignmentId;
        $existing = $this->deliveryTaskRepository->lockForConsignment($actor->hqId, $consignmentId);
        if ($existing !== null) {
            if ((string) $existing->node_id !== $nodeId) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.delivery_task_belongs_another_resolved_last_mile');
            }

            return (string) $existing->delivery_task_id;
        }
        $consignment = $this->consignmentLedgerAccess->lockConsignment($actor->hqId, $consignmentId);
        if ($consignment === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $evidence = $this->routePlanRepository->findLastMileResolution($actor->hqId, $consignmentId);
        if ($evidence === null && $consignment->delivery_node_id === null && $this->deliveryNodeCapabilities->nodeHasCapability((string) $actor->hqId, $nodeId, 'GATEWAY')) {
            $this->lastMileResolver->resolveLastMile($actor, $nodeId, $consignment);
            $evidence = $this->routePlanRepository->findLastMileResolution($actor->hqId, $consignmentId);
            if ((string) $evidence->last_mile_node_id !== $nodeId) {
                return '';
            }
        }
        if ($evidence !== null && (string) $evidence->last_mile_node_id !== $nodeId) {
            return '';
        }
        if ($consignment->delivery_node_id !== null && (string) $consignment->delivery_node_id !== $nodeId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.delivery_tasks_can_be_created_only_resolved');
        }
        $id = (string) DeliveryTaskRecord::query()->forceCreate([

            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
            'node_id' => $nodeId,
            'last_mile_resolution_id' => $evidence?->last_mile_resolution_id,
            'status' => 'PENDING',
            'attempt_number' => 1,
            'version' => 1,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ])->getKey();
        $this->deliveryTaskRecorder->history($actor, $id, $consignmentId, 'CREATED', null, 'PENDING', 1);
        $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_CREATED', $id, $consignmentId, 'PENDING', $this->correlationIdProvider->current());

        return $id;
    }
}
