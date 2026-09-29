<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface PickupTaskRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $command, string $id, string $consignmentId, string $status, string $correlationId): void;
}
