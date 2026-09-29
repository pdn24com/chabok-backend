<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\Catalogs\AuthorizationCatalog;
use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RolePermissionRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;

final class AuthorizationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedPermissions();
            $this->seedRoles();
            $this->seedGrants();
        });
    }

    private function seedPermissions(): void
    {
        $rows = [];
        $at = now();
        foreach (AuthorizationCatalog::permissions() as $code => $module) {
            $separator = strrpos($code, '.');
            $rows[] = [

                'permission_code' => $code,
                'module_code' => $module,
                'resource_code' => substr($code, 0, $separator),
                'action_code' => substr($code, $separator + 1),
                'description' => "Approved {$code} permission.",
                'status' => 'ACTIVE',
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }
        // Existing public identity and original creation time are never replaced.
        PermissionRecord::query()->upsert($rows, ['permission_code'], [
            'module_code', 'resource_code', 'action_code', 'description', 'status', 'updated_at',
        ]);
    }

    private function seedRoles(): void
    {
        $rows = [];
        $at = now();
        foreach (AuthorizationCatalog::roles() as $code => $definition) {
            $rows[] = [

                'owner_key' => 'GLOBAL',
                'role_code' => $code,
                'hq_id' => null,
                'role_title' => $definition['title'],
                'description' => "Approved {$definition['kind']} role.",
                'role_kind' => $definition['kind'],
                'is_cloneable' => $definition['cloneable'],
                'status' => 'ACTIVE',
                'created_by' => null,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }
        RoleRecord::query()->upsert($rows, ['owner_key', 'role_code'], [
            'role_title', 'description', 'role_kind', 'is_cloneable', 'status', 'updated_at',
        ]);
    }

    private function seedGrants(): void
    {
        $grants = AuthorizationCatalog::grants();
        $roles = RoleRecord::query()->where('owner_key', 'GLOBAL')
            ->whereIn('role_code', array_keys($grants))->pluck('role_id', 'role_code');
        $permissions = PermissionRecord::query()->pluck('permission_id', 'permission_code');
        $expected = [];
        $rows = [];
        $at = now();
        foreach ($grants as $roleCode => $permissionCodes) {
            $roleId = $roles[$roleCode];
            foreach ($permissionCodes as $permissionCode) {
                $permissionId = $permissions[$permissionCode];
                $expected[$roleId][$permissionId] = true;
                $rows[] = [

                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_by' => null,
                    'created_at' => $at,
                ];
            }
        }
        $obsoleteIds = [];
        $existing = RolePermissionRecord::query()->whereIn('role_id', $roles->values())->get();
        foreach ($existing as $grant) {
            if (! isset($expected[$grant->role_id][$grant->permission_id])) {
                $obsoleteIds[] = $grant->getKey();
            }
        }
        if ($obsoleteIds !== []) {
            RolePermissionRecord::query()->whereKey($obsoleteIds)->delete();
        }
        if ($rows !== []) {
            RolePermissionRecord::query()->upsert($rows, ['role_id', 'permission_id'], ['created_by']);
        }
    }
}
