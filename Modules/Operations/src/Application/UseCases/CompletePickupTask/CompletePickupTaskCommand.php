<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompletePickupTask;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CompletePickupTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $expected,
        public string $correlationId,
    ) {}
}
