<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

interface RolePermissionWriterInterface
{
    public function insertRolePermissions(string $roleId, array $permissionCodes, string $actorId): void;
}
