<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use DateTimeInterface;
use Modules\Authorization\Application\Repositories\RoleNavigationRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleMenuItemRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleMenuPreferenceRecord;

final class EloquentRoleNavigationRepository implements RoleNavigationRepositoryInterface
{
    public function hasPreference(string $roleId): bool
    {
        return RoleMenuPreferenceRecord::query()->where('role_id', $roleId)->exists();
    }

    public function preferenceCount(array $roleIds): int
    {
        return RoleMenuPreferenceRecord::query()->whereIn('role_id', $roleIds)->count();
    }

    public function selectedKeys(array $roleIds): array
    {
        return RoleMenuItemRecord::query()
            ->whereIn('role_id', $roleIds)
            ->distinct()
            ->orderBy('menu_key')
            ->pluck('menu_key')
            ->all();
    }

    public function deleteItems(string $roleId): void
    {
        RoleMenuItemRecord::query()->where('role_id', $roleId)->delete();
    }

    public function deletePreference(string $roleId): void
    {
        RoleMenuPreferenceRecord::query()->where('role_id', $roleId)->delete();
    }

    public function touchPreference(string $roleId, DateTimeInterface $at): void
    {
        RoleMenuPreferenceRecord::query()->insertOrIgnore([
            'role_id' => $roleId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        RoleMenuPreferenceRecord::query()->where('role_id', $roleId)->update(['updated_at' => $at]);
    }

    public function insertItems(string $roleId, array $menuKeys): void
    {
        RoleMenuItemRecord::query()->insert(array_map(
            fn (string $key): array => ['role_id' => $roleId, 'menu_key' => $key],
            $menuKeys,
        ));
    }
}
