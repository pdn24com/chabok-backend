<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompleteDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CompleteDeliveryTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $expected,
        public string $recipientName,
        public string $deliveredAt,
        public ?string $note,
        public string $correlationId,
    )
    {
    }
}
