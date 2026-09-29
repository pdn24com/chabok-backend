<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface DeliveryTaskRecorderInterface
{
    public function history(AuthenticatedPrincipal $actor, string $taskId, string $consignmentId, string $event, ?string $from, string $to, int $attempt, ?string $driverId = null, ?string $reasonCode = null, ?string $safeNote = null, ?array $metadata = null): void;

    public function record(AuthenticatedPrincipal $actor, string $command, string $id, string $consignmentId, string $status, string $correlationId): void;
}
