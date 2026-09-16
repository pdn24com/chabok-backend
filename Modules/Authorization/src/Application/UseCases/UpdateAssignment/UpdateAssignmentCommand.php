<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateAssignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateAssignmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $assignmentId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
