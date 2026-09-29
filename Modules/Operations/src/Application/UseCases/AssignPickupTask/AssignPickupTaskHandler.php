<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignPickupTask;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\ParcelLifecycleServiceInterface;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;
use Modules\Operations\Application\Contracts\PickupTaskGuardInterface;
use Modules\Operations\Application\Contracts\PickupTaskReaderInterface;
use Modules\Operations\Application\Contracts\PickupTaskRecorderInterface;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler;
use Modules\Operations\Domain\Enums\PickupTaskStatus;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final readonly class AssignPickupTaskHandler
{
    public function __construct(
        private PickupAccessGuardInterface $pickupAccessGuard,
        private ConnectionInterface $connection,
        private PickupTaskReaderInterface $pickupTaskReader,
        private PickupTaskGuardInterface $pickupTaskGuard,
        private ParcelLifecycleServiceInterface $parcelLifecycleService,
        private ClockInterface $clock,
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private PickupTaskRecorderInterface $pickupTaskRecorder,
        private GetPickupTaskHandler $getPickupTaskHandler,
    ) {}

    public function handle(AssignPickupTaskCommand $command): PickupTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $driverId = $command->driverId;
        $expected = $command->expected;
        $correlationId = $command->correlationId;
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.assign');
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $driverId, $expected, $correlationId): void {
            $task = $this->pickupTaskReader->locked($actor, $nodeId, $id);
            $this->pickupTaskGuard->version($task, $expected);
            if ($task->status !== PickupTaskStatus::Pending) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.pickup_task_cannot_be_assigned_current_state');
            }
            $this->pickupTaskGuard->eligibleDriver($actor, $nodeId, $driverId, 'PICKUP');
            $this->parcelLifecycleService->transition($actor, (string) $task->consignment_id, ConsignmentStatus::Confirmed->value, ConsignmentStatus::PickupAssigned->value, 'PICKUP_ASSIGNED', null, CustodyType::PickupDriver->value, $driverId, $correlationId, $driverId);
            $task->forceFill([
                'assigned_driver_id' => $driverId,
                'status' => 'ASSIGNED',
                'version' => $expected + 1,
                'assigned_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $this->consignmentLedgerAccess->setPickupDriver($task->consignment_id, $driverId);
            $this->pickupTaskRecorder->record($actor, 'PICKUP_TASK_ASSIGNED', $id, (string) $task->consignment_id, 'ASSIGNED', $correlationId);
        }, attempts: 3);

        return $this->getPickupTaskHandler->handle(new GetPickupTaskCommand($actor, $nodeId, $id));
    }
}
