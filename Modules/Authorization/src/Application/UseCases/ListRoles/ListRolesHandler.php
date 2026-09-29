<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListRoles;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;

final readonly class ListRolesHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private RoleReaderInterface $roleReader,
    ) {}

    public function handle(ListRolesCommand $command): Collection
    {
        $actor = $command->actor;
        $this->authorizationGuard->assertRoleReadAccess($actor);

        return $this->roleReader->visibleRoles($actor->hqId);
    }
}
