<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\EnsurePendingDelivery;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class EnsurePendingDeliveryHandler
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Operations\Application\Services\DeliveryNodeCapabilities $deliveryNodeCapabilities,
        private \Modules\Operations\Application\Services\LastMileResolver $lastMileResolver,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\DeliveryTaskRecorder $deliveryTaskRecorder,
        private \Modules\Foundation\Application\Contracts\CorrelationIdProvider $correlation,
    )
    {
    }

    public function handle(EnsurePendingDeliveryCommand $command): EnsurePendingDeliveryResult
    {
        return new EnsurePendingDeliveryResult($this->execute($command->actor, $command->nodeId, $command->consignmentId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId): string
    {
        $existing = $this->tasks->lockForConsignment($actor->hqId, $consignmentId);
        if ($existing !== null) {
            if ((string) $existing->node_id !== $nodeId) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task belongs to another resolved Last-mile Node.');
            }
            return (string) $existing->delivery_task_id;
        }
        $consignment = $this->ledger->lockConsignment($actor->hqId, $consignmentId);
        if ($consignment === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $evidence = $this->tasks->tenantResolution($actor->hqId, $consignmentId);
        if ($evidence === null && $consignment->delivery_node_id === null && $this->deliveryNodeCapabilities->nodeHasCapability((string) $actor->hqId, $nodeId, 'GATEWAY')) {
            $this->lastMileResolver->resolveLastMile($actor, $nodeId, $consignment);
            $evidence = $this->tasks->tenantResolution($actor->hqId, $consignmentId);
            if ((string) $evidence->last_mile_node_id !== $nodeId) {
                return '';
            }
        }
        if ($evidence !== null && (string) $evidence->last_mile_node_id !== $nodeId) {
            return '';
        }
        if ($consignment->delivery_node_id !== null && (string) $consignment->delivery_node_id !== $nodeId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Delivery Tasks can be created only at the resolved Last-mile Node.');
        }
        $id = $this->identifiers->uuid();
        $this->tasks->insert([
            'delivery_task_id' => $id,
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
            'node_id' => $nodeId,
            'last_mile_resolution_id' => $evidence?->last_mile_resolution_id,
            'status' => 'PENDING',
            'attempt_number' => 1,
            'version' => 1,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
        $this->deliveryTaskRecorder->history($actor, $id, $consignmentId, 'CREATED', null, 'PENDING', 1);
        $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_CREATED', $id, $consignmentId, 'PENDING', $this->correlation->current());
        return $id;
    }
}
