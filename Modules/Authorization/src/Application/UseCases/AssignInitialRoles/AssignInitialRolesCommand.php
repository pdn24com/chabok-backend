<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\AssignInitialRoles;

final readonly class AssignInitialRolesCommand
{
    public function __construct(
        public string $hqId,
        public string $userId,
        public string $actorId,
        public array $assignments,
        public string $correlationId,
    )
    {
    }
}
