<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\PickupTaskRecorderInterface;

final readonly class PickupTaskRecorder implements PickupTaskRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $command,
        string $id,
        string $consignmentId,
        string $status,
        string $correlationId,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $command, 'PICKUP_TASK', $id, $correlationId, after: ['status' => $status], sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'PICKUP_TASK', $id, 'operations.command.executed', $correlationId, [
            'command' => $command,
            'resource_id' => $id,
            'consignment_id' => $consignmentId,
            'status' => $status,
        ]);
    }
}
