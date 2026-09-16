<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompleteDeliveryTask;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CompleteDeliveryTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\DeliveryAccessGuard $deliveryAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\DeliveryTaskReader $deliveryTaskReader,
        private \Modules\Operations\Application\Services\DeliveryAssignmentGuard $deliveryAssignmentGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\ParcelLifecycleService $lifecycle,
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Operations\Application\Services\DeliveryTaskRecorder $deliveryTaskRecorder,
        private \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler $getDeliveryTask,
    )
    {
    }

    public function handle(CompleteDeliveryTaskCommand $command): CompleteDeliveryTaskResult
    {
        return new CompleteDeliveryTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->recipientName, $command->deliveredAt, $command->note, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $recipientName,
        string $deliveredAt,
        ?string $note,
        string $correlationId,
    ): array
    {
        $this->deliveryAccessGuard->executionAccess($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $recipientName, $deliveredAt, $note, $correlationId): void {
            $task = $this->deliveryTaskReader->locked($actor, $nodeId, $id);
            $this->deliveryAssignmentGuard->version($task, $expected);
            if ((string) $task->status !== 'IN_PROGRESS') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Delivery completion is allowed only after the approved Delivery Manifest activates the Task.');
            }
            $delivered = CarbonImmutable::parse($deliveredAt)->utc();
            if ($delivered > $this->clock->now()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Delivery time cannot be in the future.');
            }
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'OD', 'OK', 'DELIVERY_COMPLETED', null, 'RECIPIENT', null, $correlationId, (string) $task->assigned_driver_id, (string) $task->manifest_id, safeNote: $note);
            $this->tasks->updateVersion($id, $expected, [
                'status' => 'COMPLETED',
                'recipient_name' => trim($recipientName),
                'proof_type' => 'MANUAL_CONFIRMATION',
                'proof_note' => $note,
                'delivered_at' => $delivered->format('Y-m-d H:i:s.u'),
                'version' => $expected + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->tasks->updateDriverAvailability($actor->hqId, $task->assigned_driver_id, 'ON_MISSION', ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
            $this->deliveryTaskRecorder->history($actor, $id, (string) $task->consignment_id, 'COMPLETED', 'IN_PROGRESS', 'COMPLETED', (int) $task->attempt_number, (string) $task->assigned_driver_id, safeNote: $note, metadata: [
                'recipient_name' => trim($recipientName),
                'proof_type' => 'MANUAL_CONFIRMATION',
                'delivered_at' => $delivered->toISOString(),
            ]);
            $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_COMPLETED', $id, (string) $task->consignment_id, 'COMPLETED', $correlationId);
        });
        return $this->getDeliveryTask->handle(new \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand($actor, $nodeId, $id))->data;
    }
}
