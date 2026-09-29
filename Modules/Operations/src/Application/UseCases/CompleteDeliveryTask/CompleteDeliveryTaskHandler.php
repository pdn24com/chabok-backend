<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompleteDeliveryTask;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryAssignmentGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskReaderInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Contracts\ParcelLifecycleServiceInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final readonly class CompleteDeliveryTaskHandler
{
    public function __construct(
        private DeliveryAccessGuardInterface $deliveryAccessGuard,
        private ConnectionInterface $connection,
        private DeliveryTaskReaderInterface $deliveryTaskReader,
        private DeliveryAssignmentGuardInterface $deliveryAssignmentGuard,
        private ClockInterface $clock,
        private ParcelLifecycleServiceInterface $parcelLifecycleService,
        private DeliveryTaskRecorderInterface $deliveryTaskRecorder,
        private GetDeliveryTaskHandler $getDeliveryTaskHandler,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function handle(CompleteDeliveryTaskCommand $command): DeliveryTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $expected = $command->expected;
        $recipientName = $command->recipientName;
        $deliveredAt = $command->deliveredAt;
        $note = $command->note;
        $correlationId = $command->correlationId;
        $this->deliveryAccessGuard->executionAccess($actor, $nodeId, $id);
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $expected, $recipientName, $deliveredAt, $note, $correlationId): void {
            $task = $this->deliveryTaskReader->locked($actor, $nodeId, $id);
            $this->deliveryAssignmentGuard->version($task, $expected);
            if ($task->status->value !== 'IN_PROGRESS') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.delivery_completion_is_allowed_only_after_approved');
            }
            $delivered = CarbonImmutable::parse($deliveredAt)->utc();
            if ($delivered > $this->clock->now()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.delivery_time_cannot_be_future');
            }
            $this->parcelLifecycleService->transition($actor, (string) $task->consignment_id, ConsignmentStatus::OutForDelivery->value, ConsignmentStatus::Delivered->value, 'DELIVERY_COMPLETED', null, CustodyType::Recipient->value, null, $correlationId, (string) $task->assigned_driver_id, (string) $task->manifest_id, safeNote: $note);
            $this->deliveryTaskRepository->updateExpectedVersion($id, $expected, [
                'status' => 'COMPLETED',
                'recipient_name' => trim($recipientName),
                'proof_type' => 'MANUAL_CONFIRMATION',
                'proof_note' => $note,
                'delivered_at' => $delivered->format('Y-m-d H:i:s.u'),
                'version' => $expected + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->driverRepository->endMission($actor->hqId, $task->assigned_driver_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
            $this->deliveryTaskRecorder->history($actor, $id, (string) $task->consignment_id, 'COMPLETED', 'IN_PROGRESS', 'COMPLETED', (int) $task->attempt_number, (string) $task->assigned_driver_id, safeNote: $note, metadata: [
                'recipient_name' => trim($recipientName),
                'proof_type' => 'MANUAL_CONFIRMATION',
                'delivered_at' => $delivered->toISOString(),
            ]);
            $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_COMPLETED', $id, (string) $task->consignment_id, 'COMPLETED', $correlationId);
        }, attempts: 3);

        return $this->getDeliveryTaskHandler->handle(new GetDeliveryTaskCommand($actor, $nodeId, $id));
    }
}
