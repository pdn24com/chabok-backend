<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetRole;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;

final readonly class GetRoleHandler
{
    public function __construct(private AuthorizationGuardInterface $authorizationGuard, private RoleReaderInterface $roleReader) {}

    public function handle(GetRoleCommand $command): RoleRecord
    {
        $actor = $command->actor;
        $roleId = $command->roleId;
        $this->authorizationGuard->assertRoleReadAccess($actor);
        $this->authorizationGuard->assertVisibleRole($roleId, $actor->hqId);

        return $this->roleReader->role($roleId);
    }
}
