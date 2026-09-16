<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateAssignments;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateAssignmentsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public array $assignments,
        public string $correlationId,
    )
    {
    }
}
