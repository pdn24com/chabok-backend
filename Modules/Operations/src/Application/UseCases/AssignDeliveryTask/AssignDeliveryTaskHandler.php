<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AssignDeliveryTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\DeliveryAccessGuard $deliveryAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\DeliveryTaskReader $deliveryTaskReader,
        private \Modules\Operations\Application\Services\DeliveryAssignmentGuard $deliveryAssignmentGuard,
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Operations\Application\Services\DeliveryTaskRecorder $deliveryTaskRecorder,
        private \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler $getDeliveryTask,
    )
    {
    }

    public function handle(AssignDeliveryTaskCommand $command): AssignDeliveryTaskResult
    {
        return new AssignDeliveryTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->driverId, $command->expected, $command->correlationId));
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
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $driverId, $expected, $correlationId): void {
            $task = $this->deliveryTaskReader->locked($actor, $nodeId, $id);
            $this->deliveryAssignmentGuard->version($task, $expected);
            if (!in_array((string) $task->status, ['PENDING', 'ASSIGNED'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a pending or not-yet-activated Delivery Task can be assigned.');
            }
            if ((string) $task->status === 'ASSIGNED' && (string) $task->assigned_driver_id === $driverId) {
                return;
            }
            $this->deliveryAssignmentGuard->eligibleDriver($actor, $nodeId, $driverId, $id);
            $event = $task->assigned_driver_id === null ? 'ASSIGNED' : 'REASSIGNED';
            $this->tasks->updateVersion($id, $expected, [
                'assigned_driver_id' => $driverId,
                'status' => 'ASSIGNED',
                'version' => $expected + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->ledger->setDeliveryDriver($task->consignment_id, $driverId);
            $this->deliveryTaskRecorder->history($actor, $id, (string) $task->consignment_id, $event, (string) $task->status, 'ASSIGNED', (int) $task->attempt_number, $driverId, metadata: ['previous_driver_id' => $task->assigned_driver_id]);
            $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_' . $event, $id, (string) $task->consignment_id, 'ASSIGNED', $correlationId);
        });
        return $this->getDeliveryTask->handle(new \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand($actor, $nodeId, $id))->data;
    }
}
