<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RolePermissionWriter
{
    public function __construct(
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function insertRolePermissions(string $roleId, array $permissionCodes, string $actorId): void
    {
        if ($permissionCodes === []) {
            return;
        }
        $permissions = $this->repository->activePermissions($permissionCodes);
        if (count($permissions) !== count(array_unique($permissionCodes))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An unknown permission was supplied.');
        }
        foreach ($permissions as $permission) {
            $this->repository->insertRolePermission([
                'role_permission_id' => $this->identifiers->uuid(),
                'role_id' => $roleId,
                'permission_id' => $permission->permission_id,
                'created_by' => $actorId,
                'created_at' => $this->clock->now(),
            ]);
        }
    }
}
