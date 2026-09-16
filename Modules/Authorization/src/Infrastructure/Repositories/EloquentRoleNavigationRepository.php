<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\Repositories\RoleNavigationRepository;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleMenuPreferenceRecord;

final class EloquentRoleNavigationRepository implements RoleNavigationRepository
{
    public function selection(string $roleId): ?array
    {
        if (!RoleMenuPreferenceRecord::query()->where('role_id', $roleId)->exists()) {
            return null;
        }
        return $this->selectedKeys([$roleId]);
    }

    public function replace(string $roleId, ?array $keys, \DateTimeImmutable $at): void
    {
        // Composite-key relationship rows are written explicitly inside the caller's transaction.
        DB::table('role_menu_items')->where('role_id', $roleId)->delete();
        if ($keys === null) {
            RoleMenuPreferenceRecord::query()->where('role_id', $roleId)->delete();
            return;
        }
        RoleMenuPreferenceRecord::query()->insertOrIgnore(['role_id' => $roleId, 'created_at' => $at, 'updated_at' => $at]);
        RoleMenuPreferenceRecord::query()->where('role_id', $roleId)->update(['updated_at' => $at]);
        foreach ($keys as $key) {
            DB::table('role_menu_items')->insert(['role_id' => $roleId, 'menu_key' => $key]);
        }
    }

    public function configuredRoleCount(array $roleIds): int
    {
        return RoleMenuPreferenceRecord::query()->whereIn('role_id', $roleIds)->count();
    }

    public function selectedKeys(array $roleIds): array
    {
        return DB::table('role_menu_items')->whereIn('role_id', $roleIds)->distinct()->orderBy('menu_key')->pluck('menu_key')->all();
    }
}
