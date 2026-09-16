<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AssignDeliveryTaskCommand
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
