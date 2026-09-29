<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateAssignment;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;

final readonly class UpdateAssignmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $assignmentId,
        public RoleAssignment $input,
        public string $correlationId,
    ) {}
}
