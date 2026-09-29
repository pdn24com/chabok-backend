<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\AssignInitialRoles;

use Modules\Foundation\Domain\ValueObjects\RoleAssignment;

final readonly class AssignInitialRolesCommand
{
    /** @param list<RoleAssignment> $assignments */
    public function __construct(
        public string $hqId,
        public string $userId,
        public string $actorId,
        public array $assignments,
        public string $correlationId,
    ) {}
}
