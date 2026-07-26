<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authorization\Application\AuthorizationCatalog;

final class AuthorizationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (AuthorizationCatalog::permissions() as $code => $module) {
                $separator = strrpos($code, '.');
                $resource = substr($code, 0, $separator);
                $action = substr($code, $separator + 1);
                DB::table('permissions')->updateOrInsert(
                    ['permission_code' => $code],
                    [
                        'permission_id' => $this->existingId('permissions', 'permission_code', $code, 'permission_id'),
                        'module_code' => $module,
                        'resource_code' => $resource,
                        'action_code' => $action,
                        'description' => "Approved {$code} permission.",
                        'status' => 'ACTIVE',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            foreach (AuthorizationCatalog::roles() as $code => $definition) {
                DB::table('roles')->updateOrInsert(
                    ['owner_key' => 'GLOBAL', 'role_code' => $code],
                    [
                        'role_id' => $this->existingId('roles', 'role_code', $code, 'role_id'),
                        'hq_id' => null,
                        'role_title' => $definition['title'],
                        'description' => "Approved {$definition['kind']} role.",
                        'role_kind' => $definition['kind'],
                        'is_cloneable' => $definition['cloneable'],
                        'status' => 'ACTIVE',
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            foreach (AuthorizationCatalog::grants() as $roleCode => $permissionCodes) {
                $roleId = DB::table('roles')->where('owner_key', 'GLOBAL')
                    ->where('role_code', $roleCode)->value('role_id');
                $permissionIds = DB::table('permissions')
                    ->whereIn('permission_code', $permissionCodes)
                    ->pluck('permission_id');
                DB::table('role_permissions')->where('role_id', $roleId)
                    ->whereNotIn('permission_id', $permissionIds)->delete();
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $permissionId],
                        [
                            'role_permission_id' => $this->existingJoinId((string) $roleId, (string) $permissionId),
                            'created_by' => null,
                            'created_at' => now(),
                        ],
                    );
                }
            }
        });
    }

    private function existingId(string $table, string $key, string $value, string $id): string
    {
        return (string) (DB::table($table)->where($key, $value)->value($id) ?: Str::uuid());
    }

    private function existingJoinId(string $roleId, string $permissionId): string
    {
        return (string) (DB::table('role_permissions')->where([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ])->value('role_permission_id') ?: Str::uuid());
    }
}
