<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignPickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AssignPickupTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public string $driverId,
        public int $expected,
        public string $correlationId,
    )
    {
    }
}
