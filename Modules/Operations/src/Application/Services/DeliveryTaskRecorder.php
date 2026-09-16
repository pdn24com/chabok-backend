<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class DeliveryTaskRecorder
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

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
    ): void
    {
        $sequence = (int) $this->tasks->lastHistorySequence($taskId) + 1;
        $this->tasks->appendHistory([
            'delivery_task_history_id' => $this->identifiers->uuid(),
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
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'occurred_at' => $this->clock->now(),
        ]);
    }

    public function record(
        AuthenticatedPrincipal $actor,
        string $command,
        string $id,
        string $consignmentId,
        string $status,
        string $correlationId,
    ): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $command, 'DELIVERY_TASK', $id, $correlationId, after: ['status' => $status], sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'DELIVERY_TASK', $id, 'operations.command.executed', $correlationId, ['command' => $command, 'resource_id' => $id, 'consignment_id' => $consignmentId, 'status' => $status]);
    }
}
