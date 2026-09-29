<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;

final class EloquentRoleRepository implements RoleRepositoryInterface
{
    /** Everything a Role response renders, loaded up front. */
    private const GRANTS = ['permissions', 'menuPreference', 'menuItems'];

    public function find(string $roleId): ?RoleRecord
    {
        return RoleRecord::query()->where('role_id', $roleId)->first();
    }

    public function lock(string $roleId): ?RoleRecord
    {
        return RoleRecord::query()->where('role_id', $roleId)->lockForUpdate()->first();
    }

    public function findWithGrants(string $roleId): ?RoleRecord
    {
        return RoleRecord::query()->with(self::GRANTS)->where('role_id', $roleId)->first();
    }

    public function visibleWithGrants(?string $hqId): Collection
    {
        return RoleRecord::query()->with(self::GRANTS)
            ->where(fn ($query) => $query->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->orderBy('role_kind')->orderBy('role_code')
            ->get();
    }

    public function activeWithPermissionsKeyedById(array $roleIds): Collection
    {
        return RoleRecord::query()->whereIn('role_id', $roleIds)->where('status', 'ACTIVE')->with('permissions')->get()->keyBy('role_id');
    }

    public function isAssignable(string $roleId): bool
    {
        return RoleRecord::query()
            ->where('role_id', $roleId)
            ->where('status', 'ACTIVE')
            ->where('role_code', '!=', 'platform_super_admin')
            ->exists();
    }

    public function insert(array $attributes): string
    {
        return (string) RoleRecord::query()->forceCreate($attributes)->getKey();
    }

    public function apply(RoleRecord $role, array $changes): void
    {
        $role->forceFill($changes)->save();
    }
}
