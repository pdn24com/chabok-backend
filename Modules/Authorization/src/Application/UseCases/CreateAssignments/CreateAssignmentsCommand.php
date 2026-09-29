<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateAssignments;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;

final readonly class CreateAssignmentsCommand
{
    /** @param list<RoleAssignment> $assignments */
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public array $assignments,
        public string $correlationId,
    ) {}
}
