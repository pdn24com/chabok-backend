<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class FailDeliveryTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $expected,
        public string $reasonCode,
        public string $reason,
        public string $correlationId,
    )
    {
    }
}
