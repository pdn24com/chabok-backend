<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Authorization\Application\Contracts\RolePermissionWriterInterface;
use Modules\Authorization\Application\Repositories\PermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RolePermissionRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class RolePermissionWriter implements RolePermissionWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private PermissionRepositoryInterface $permissionRepository,
        private RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {}

    public function insertRolePermissions(
        string $roleId,
        array $permissionCodes,
        string $actorId,
    ): void {
        if ($permissionCodes === []) {
            return;
        }
        $permissions = $this->permissionRepository->activeByCodes($permissionCodes);
        if (count($permissions) !== count(array_unique($permissionCodes))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.unknown_permission_supplied');
        }
        $at = $this->clock->now();
        $rows = [];
        foreach ($permissions as $permission) {
            $rows[] = [

                'role_id' => $roleId,
                'permission_id' => $permission->permission_id,
                'created_by' => $actorId,
                'created_at' => $at,
            ];
        }
        foreach (array_chunk($rows, 100) as $batch) {
            $this->rolePermissionRepository->insert($batch);
        }
    }
}
