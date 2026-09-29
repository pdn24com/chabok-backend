<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompletePickupTask;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
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
use Modules\ServiceCatalog\Application\Contracts\FrozenCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Dto\FrozenDeliveryCommitmentDto;
use Modules\ServiceCatalog\Application\Serialization\CommitmentResolutionDocument;

final readonly class CompletePickupTaskHandler
{
    public function __construct(
        private PickupAccessGuardInterface $pickupAccessGuard,
        private ConnectionInterface $connection,
        private PickupTaskReaderInterface $pickupTaskReader,
        private PickupTaskGuardInterface $pickupTaskGuard,
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private ClockInterface $clock,
        private FrozenCommitmentResolverInterface $frozenCommitmentResolver,
        private AuditWriterInterface $auditWriter,
        private ParcelLifecycleServiceInterface $parcelLifecycleService,
        private PickupTaskRecorderInterface $pickupTaskRecorder,
        private GetPickupTaskHandler $getPickupTaskHandler,
    ) {}

    public function handle(CompletePickupTaskCommand $command): PickupTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $expected = $command->expected;
        $correlationId = $command->correlationId;
        $this->pickupAccessGuard->accessExecution($actor, $nodeId, $id);
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $task = $this->pickupTaskReader->locked($actor, $nodeId, $id);
            $this->pickupTaskGuard->version($task, $expected);
            if (! in_array($task->status, [PickupTaskStatus::Assigned, PickupTaskStatus::InProgress], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.pickup_task_cannot_be_completed_current_state');
            }
            $consignment = $this->consignmentLedgerAccess->lockConsignment($actor->hqId, $task->consignment_id);
            $snapshot = $consignment->commitment_snapshot;
            $completionTime = $this->clock->now();
            $completedAt = $completionTime
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.u\Z');
            $frozen = FrozenDeliveryCommitmentDto::fromSnapshot($snapshot);
            $resolution = $frozen === null ? null : $this->frozenCommitmentResolver->pickupCompleted($frozen, $completionTime);
            if ($resolution) {
                $end = $resolution->selected?->endsAt ?? $resolution->computedAt;
                $start = $resolution->selected?->startsAt ?? $resolution->computedAt;
                $document = CommitmentResolutionDocument::serialize($resolution, $snapshot['effective_delivery_policy']);
                $consignment->forceFill([
                    'delivery_commitment_at' => $end?->utc()->format('Y-m-d H:i:s.u'),
                    'delivery_commitment_end_at' => $end?->utc()->format('Y-m-d H:i:s.u'),
                    'delivery_commitment_start_at' => $start?->utc()->format('Y-m-d H:i:s.u'),
                    'delivery_commitment_resolution' => ['pickup_completed_at' => $completedAt, 'result' => $document],
                ])->save();
                $this->auditWriter->write($actor->hqId, $actor->userId, 'CONSIGNMENT_COMMITMENT_RESOLVED', 'CONSIGNMENT', (string) $task->consignment_id, $correlationId, after: ['pickup_completed_at' => $completedAt, 'delivery' => $document], sourceClient: SourceClient::BranchPanel->value);
            }
            $this->parcelLifecycleService->transition($actor, (string) $task->consignment_id, ConsignmentStatus::PickupAssigned->value, ConsignmentStatus::PickedUp->value, 'PICKUP_COMPLETED', null, CustodyType::PickupDriver->value, (string) $task->assigned_driver_id, $correlationId, (string) $task->assigned_driver_id);
            $task->forceFill([
                'status' => 'COMPLETED',
                'version' => $expected + 1,
                'completed_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $this->pickupTaskRecorder->record($actor, 'PICKUP_TASK_COMPLETED', $id, (string) $task->consignment_id, 'COMPLETED', $correlationId);
        }, attempts: 3);

        return $this->getPickupTaskHandler->handle(new GetPickupTaskCommand($actor, $nodeId, $id));
    }
}
