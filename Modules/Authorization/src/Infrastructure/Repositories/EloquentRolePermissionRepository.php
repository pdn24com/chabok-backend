<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Application\Repositories\RolePermissionRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\RolePermissionRecord;

final class EloquentRolePermissionRepository implements RolePermissionRepositoryInterface
{
    public function insert(array $rows): void
    {
        RolePermissionRecord::query()->insert($rows);
    }

    public function deleteForRole(string $roleId): void
    {
        RolePermissionRecord::query()->where('role_id', $roleId)->delete();
    }

    public function activeGrantsForRoles(array $roleIds): Collection
    {
        return RolePermissionRecord::query()->with('permission')->whereIn('role_id', $roleIds)
            ->whereHas('permission', fn ($permission) => $permission->where('status', 'ACTIVE'))->get();
    }
}
