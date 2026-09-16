<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignPickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AssignPickupTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\PickupAccessGuard $pickupAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\PickupTaskReader $pickupTaskReader,
        private \Modules\Operations\Application\Services\PickupTaskGuard $pickupTaskGuard,
        private \Modules\Operations\Application\ParcelLifecycleService $lifecycle,
        private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Operations\Application\Services\PickupTaskRecorder $pickupTaskRecorder,
        private \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler $getPickupTask,
    )
    {
    }

    public function handle(AssignPickupTaskCommand $command): AssignPickupTaskResult
    {
        return new AssignPickupTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->driverId, $command->expected, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        string $driverId,
        int $expected,
        string $correlationId,
    ): array
    {
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.assign');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $driverId, $expected, $correlationId): void {
            $task = $this->pickupTaskReader->locked($actor, $nodeId, $id);
            $this->pickupTaskGuard->version($task, $expected);
            if ($task->status !== 'PENDING') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup Task cannot be assigned in its current state.');
            }
            $this->pickupTaskGuard->eligibleDriver($actor, $nodeId, $driverId, 'PICKUP');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'CFM', 'PD', 'PICKUP_ASSIGNED', null, 'PICKUP_DRIVER', $driverId, $correlationId, $driverId);
            $this->tasks->update($id, [
                'assigned_driver_id' => $driverId,
                'status' => 'ASSIGNED',
                'version' => $expected + 1,
                'assigned_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->ledger->setPickupDriver($task->consignment_id, $driverId);
            $this->pickupTaskRecorder->record($actor, 'PICKUP_TASK_ASSIGNED', $id, (string) $task->consignment_id, 'ASSIGNED', $correlationId);
        });
        return $this->getPickupTask->handle(new \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand($actor, $nodeId, $id))->data;
    }
}
