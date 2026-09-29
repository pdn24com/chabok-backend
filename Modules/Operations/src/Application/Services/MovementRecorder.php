<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\MovementRecorderInterface;
use Modules\Operations\Domain\Enums\MovementCommand;
use Modules\Operations\Domain\Enums\MovementEntityType;
use Modules\Operations\Domain\Enums\RoutePlanStatus;

final readonly class MovementRecorder implements MovementRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        MovementCommand $command,
        MovementEntityType $type,
        string $id,
        string $consignmentId,
        RoutePlanStatus $status,
        string $correlationId,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $command->value, $type->value, $id, $correlationId);
        $this->outboxWriter->write($actor->hqId, $type->value, $id, 'operations.command.executed', $correlationId, [
            'command' => $command->value,
            'resource_id' => $id,
            'consignment_id' => $consignmentId,
            'status' => $status->value,
        ]);
    }
}
