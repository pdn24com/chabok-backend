<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class MovementRecorder
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function record(
        AuthenticatedPrincipal $actor,
        string $command,
        string $type,
        string $id,
        string $consignmentId,
        string $status,
        string $correlationId,
    ): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $command, $type, $id, $correlationId);
        $this->outbox->write($actor->hqId, $type, $id, 'operations.command.executed', $correlationId, ['command' => $command, 'resource_id' => $id, 'consignment_id' => $consignmentId, 'status' => $status]);
    }
}
