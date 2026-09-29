<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\UseCases\AssignInitialRoles\AssignInitialRolesCommand;
use Modules\Authorization\Application\UseCases\AssignInitialRoles\AssignInitialRolesHandler;
use Modules\Iam\Application\Ports\InitialAssignmentWriterInterface;

final readonly class AuthorizationInitialAssignmentWriter implements InitialAssignmentWriterInterface
{
    public function __construct(private AssignInitialRolesHandler $assignInitialRolesHandler) {}

    public function assign(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void {
        $this->assignInitialRolesHandler->handle(new AssignInitialRolesCommand($hqId, $userId, $actorId, $assignments, $correlationId));
    }
}
