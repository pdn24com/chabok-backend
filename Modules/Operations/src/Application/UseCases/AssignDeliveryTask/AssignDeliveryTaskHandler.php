<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignDeliveryTask;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryAssignmentGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskReaderInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final readonly class AssignDeliveryTaskHandler
{
    public function __construct(
        private DeliveryAccessGuardInterface $deliveryAccessGuard,
        private ConnectionInterface $connection,
        private DeliveryTaskReaderInterface $deliveryTaskReader,
        private DeliveryAssignmentGuardInterface $deliveryAssignmentGuard,
        private ClockInterface $clock,
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private DeliveryTaskRecorderInterface $deliveryTaskRecorder,
        private GetDeliveryTaskHandler $getDeliveryTaskHandler,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
    ) {}

    public function handle(AssignDeliveryTaskCommand $command): DeliveryTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $driverId = $command->driverId;
        $expected = $command->expected;
        $correlationId = $command->correlationId;
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $driverId, $expected, $correlationId): void {
            $task = $this->deliveryTaskReader->locked($actor, $nodeId, $id);
            $this->deliveryAssignmentGuard->version($task, $expected);
            if (! in_array($task->status->value, ['PENDING', 'ASSIGNED'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_pending_not_yet_activated_delivery_task');
            }
            if ($task->status->value === 'ASSIGNED' && (string) $task->assigned_driver_id === $driverId) {
                return;
            }
            $this->deliveryAssignmentGuard->eligibleDriver($actor, $nodeId, $driverId, $id);
            $event = $task->assigned_driver_id === null ? 'ASSIGNED' : 'REASSIGNED';
            $this->deliveryTaskRepository->updateExpectedVersion($id, $expected, [
                'assigned_driver_id' => $driverId,
                'status' => 'ASSIGNED',
                'version' => $expected + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->consignmentLedgerAccess->setDeliveryDriver($task->consignment_id, $driverId);
            $this->deliveryTaskRecorder->history($actor, $id, (string) $task->consignment_id, $event, $task->status->value, 'ASSIGNED', (int) $task->attempt_number, $driverId, metadata: ['previous_driver_id' => $task->assigned_driver_id]);
            $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_'.$event, $id, (string) $task->consignment_id, 'ASSIGNED', $correlationId);
        }, attempts: 3);

        return $this->getDeliveryTaskHandler->handle(new GetDeliveryTaskCommand($actor, $nodeId, $id));
    }
}
