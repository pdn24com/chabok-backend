<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\RevokeAssignment;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RevokeAssignmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $assignmentId,
        public string $correlationId,
    ) {}
}
