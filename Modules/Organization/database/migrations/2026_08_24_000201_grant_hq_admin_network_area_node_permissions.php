<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = ['network.area.view', 'network.area.manage', 'network.node.view', 'network.node.manage'];

    public function up(): void
    {
        $roleId = DB::table('roles')->where(['owner_key' => 'GLOBAL', 'role_code' => 'hq_admin'])->value('role_id');
        if (! is_string($roleId)) {
            return;
        }
        foreach (DB::table('permissions')->whereIn('permission_code', self::PERMISSIONS)->get(['permission_id']) as $permission) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_permission_id' => (string) Str::uuid(), 'role_id' => $roleId,
                'permission_id' => $permission->permission_id, 'created_by' => null, 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where(['owner_key' => 'GLOBAL', 'role_code' => 'hq_admin'])->value('role_id');
        if (is_string($roleId)) {
            DB::table('role_permissions')->where('role_id', $roleId)->whereIn(
                'permission_id',
                DB::table('permissions')->whereIn('permission_code', self::PERMISSIONS)->select('permission_id'),
            )->delete();
        }
    }
};
