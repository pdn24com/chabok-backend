<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ActivateManifestDelivery;

use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryAssignmentGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;

final readonly class ActivateManifestDeliveryHandler
{
    public function __construct(
        private DeliveryAssignmentGuardInterface $deliveryAssignmentGuard,
        private ClockInterface $clock,
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private DeliveryTaskRecorderInterface $deliveryTaskRecorder,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function handle(ActivateManifestDeliveryCommand $command): string
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $consignmentId = $command->consignmentId;
        $driverId = $command->driverId;
        $manifestId = $command->manifestId;
        $task = $this->deliveryTaskRepository->lockForConsignmentAtNode($actor->hqId, $consignmentId, $nodeId);
        if ($task === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.pending_delivery_task_is_required_before_delivery');
        }
        if ($task->status->value === 'IN_PROGRESS' && (string) $task->assigned_driver_id === $driverId && (string) $task->manifest_id === $manifestId) {
            return (string) $task->delivery_task_id;
        }
        if (! in_array($task->status->value, ['PENDING', 'ASSIGNED'], true) || $task->assigned_driver_id !== null && (string) $task->assigned_driver_id !== $driverId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.delivery_task_conflicts_with_manifest_assignment');
        }
        $this->deliveryAssignmentGuard->eligibleDriverForManifest($actor, $nodeId, $driverId, $manifestId, (string) $task->delivery_task_id);
        $version = (int) $task->version + 1;
        $this->deliveryTaskRepository->update((string) $task->delivery_task_id, [
            'assigned_driver_id' => $driverId,
            'manifest_id' => $manifestId,
            'status' => 'IN_PROGRESS',
            'version' => $version,
            'updated_at' => $this->clock->now(),
        ]);
        $this->driverRepository->startMission($actor->hqId, $driverId, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
        $this->consignmentLedgerAccess->setDeliveryDriver($consignmentId, $driverId);
        $this->deliveryTaskRecorder->history($actor, (string) $task->delivery_task_id, $consignmentId, 'ACTIVATED', $task->status->value, 'IN_PROGRESS', (int) $task->attempt_number, $driverId, metadata: ['manifest_id' => $manifestId]);

        return (string) $task->delivery_task_id;
    }
}
