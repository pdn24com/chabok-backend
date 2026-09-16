<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ActivateManifestDelivery;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ActivateManifestDeliveryHandler
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Operations\Application\Services\DeliveryAssignmentGuard $deliveryAssignmentGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Operations\Application\Services\DeliveryTaskRecorder $deliveryTaskRecorder,
    )
    {
    }

    public function handle(ActivateManifestDeliveryCommand $command): ActivateManifestDeliveryResult
    {
        return new ActivateManifestDeliveryResult($this->execute($command->actor, $command->nodeId, $command->consignmentId, $command->driverId, $command->manifestId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        string $driverId,
        string $manifestId,
    ): string
    {
        $task = $this->tasks->lockAtConsignmentNode($actor->hqId, $consignmentId, $nodeId);
        if ($task === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'A pending Delivery Task is required before delivery activation.');
        }
        if ((string) $task->status === 'IN_PROGRESS' && (string) $task->assigned_driver_id === $driverId && (string) $task->manifest_id === $manifestId) {
            return (string) $task->delivery_task_id;
        }
        if (!in_array((string) $task->status, ['PENDING', 'ASSIGNED'], true) || $task->assigned_driver_id !== null && (string) $task->assigned_driver_id !== $driverId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task conflicts with the Manifest assignment.');
        }
        $this->deliveryAssignmentGuard->eligibleDriverForManifest($actor, $nodeId, $driverId, $manifestId, (string) $task->delivery_task_id);
        $version = (int) $task->version + 1;
        $this->tasks->update($task->delivery_task_id, [
            'assigned_driver_id' => $driverId,
            'manifest_id' => $manifestId,
            'status' => 'IN_PROGRESS',
            'version' => $version,
            'updated_at' => $this->clock->now(),
        ]);
        $this->tasks->updateDriverAvailability($actor->hqId, $driverId, 'AVAILABLE', ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
        $this->ledger->setDeliveryDriver($consignmentId, $driverId);
        $this->deliveryTaskRecorder->history($actor, (string) $task->delivery_task_id, $consignmentId, 'ACTIVATED', (string) $task->status, 'IN_PROGRESS', (int) $task->attempt_number, $driverId, metadata: ['manifest_id' => $manifestId]);
        return (string) $task->delivery_task_id;
    }
}
