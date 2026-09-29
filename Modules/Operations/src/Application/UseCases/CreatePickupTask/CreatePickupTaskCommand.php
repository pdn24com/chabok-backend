<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreatePickupTask;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreatePickupTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
        public string $correlationId,
    ) {}
}
