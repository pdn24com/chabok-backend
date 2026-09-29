<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskHistoryRecord;

final readonly class DeliveryTaskRecorder implements DeliveryTaskRecorderInterface
{
    public function __construct(
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
    ) {}

    public function history(
        AuthenticatedPrincipal $actor,
        string $taskId,
        string $consignmentId,
        string $event,
        ?string $from,
        string $to,
        int $attempt,
        ?string $driverId = null,
        ?string $reasonCode = null,
        ?string $safeNote = null,
        ?array $metadata = null,
    ): void {
        $sequence = $this->deliveryTaskRepository->nextHistorySequence($taskId);
        (new DeliveryTaskHistoryRecord)->forceFill([

            'hq_id' => $actor->hqId,
            'delivery_task_id' => $taskId,
            'consignment_id' => $consignmentId,
            'event_sequence' => $sequence,
            'event_type' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'attempt_number' => $attempt,
            'assigned_driver_id' => $driverId,
            'actor_id' => $actor->userId,
            'reason_code' => $reasonCode,
            'safe_note' => $safeNote,
            'metadata' => $metadata,
            'occurred_at' => $this->clock->now(),
        ])->save();
    }

    public function record(
        AuthenticatedPrincipal $actor,
        string $command,
        string $id,
        string $consignmentId,
        string $status,
        string $correlationId,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $command, 'DELIVERY_TASK', $id, $correlationId, after: ['status' => $status], sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'DELIVERY_TASK', $id, 'operations.command.executed', $correlationId, [
            'command' => $command,
            'resource_id' => $id,
            'consignment_id' => $consignmentId,
            'status' => $status,
        ]);
    }
}
