<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Modules\Authorization\Application\Repositories\AuthorizationRepository;
use Modules\Authorization\Domain\AuthorizationWriteConflict;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;

final class EloquentAuthorizationRepository implements AuthorizationRepository
{
    public function user(string $userId): ?object
    {
        return DB::table('users')->where('user_id', $userId)->first();
    }

    public function activeAssignments(string $userId, ?string $hqId): array
    {
        return AssignmentRecord::query()->toBase()->from('user_role_assignments as a')->join('roles as r', 'r.role_id', '=', 'a.role_id')->where('a.user_id', $userId)->where('a.status', 'ACTIVE')->where('r.status', 'ACTIVE')->when($hqId === null, fn($query) => $query->whereNull('a.hq_id'), fn($query) => $query->where('a.hq_id', $hqId))->select([
            'a.assignment_id',
            'a.hq_id',
            'a.scope_type',
            'a.scope_id',
            'a.includes_descendants',
            'r.role_id',
            'r.role_code',
        ])->get()->all();
    }

    public function activeGrants(array $roleIds): array
    {
        return DB::table('role_permissions as rp')->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')->whereIn('rp.role_id', $roleIds)->where('p.status', 'ACTIVE')->select(['rp.role_id', 'p.permission_code', 'p.module_code'])->distinct()->get()->all();
    }

    public function tenantEntitlements(string $hqId): array
    {
        return DB::table('tenant_module_entitlements')->where('hq_id', $hqId)->orderBy('module_code')->get(['module_code', 'status'])->all();
    }

    public function tenant(string $hqId): ?object
    {
        return DB::table('hq_tenants')->where('hq_id', $hqId)->first(['hq_id', 'hq_code', 'hq_title']);
    }

    public function activePermission(string $permissionCode): ?object
    {
        return PermissionRecord::query()->toBase()->where('permission_code', $permissionCode)->where('status', 'ACTIVE')->first();
    }

    public function entitlementStatus(string $hqId, string $moduleCode): ?string
    {
        return DB::table('tenant_module_entitlements')->where(['hq_id' => $hqId, 'module_code' => $moduleCode])->value('status');
    }

    public function activeNodes(?string $hqId, array $nodeIds): array
    {
        return DB::table('nodes')->where('hq_id', $hqId)->whereIn('node_id', $nodeIds)->where('status', 'ACTIVE')->orderBy('node_title')->get(['node_id', 'node_code', 'node_title', 'node_type', 'status'])->map(fn($row): array => (array) $row)->all();
    }

    public function nodeTenant(string $nodeId): ?object
    {
        return DB::table('nodes')->where('node_id', $nodeId)->first(['hq_id']);
    }

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return DB::table('users as u')->join('user_role_assignments as a', 'a.user_id', '=', 'u.user_id')->join('roles as r', 'r.role_id', '=', 'a.role_id')->where('u.user_id', $userId)->whereNull('u.hq_id')->where('u.status', 'ACTIVE')->whereNull('a.hq_id')->where('a.status', 'ACTIVE')->where('a.scope_type', 'PLATFORM')->whereNull('a.scope_id')->where('r.role_code', 'platform_super_admin')->where('r.status', 'ACTIVE')->exists();
    }

    public function visibleRoleIds(?string $hqId): array
    {
        return RoleRecord::query()->toBase()->where(function ($query) use ($hqId): void {
            $query->whereNull('hq_id')->orWhere('hq_id', $hqId);
        })->orderBy('role_kind')->orderBy('role_code')->pluck('role_id')->all();
    }

    public function lockedRole(string $roleId): ?object
    {
        return RoleRecord::query()->toBase()->where('role_id', $roleId)->lockForUpdate()->first();
    }

    public function removeRolePermissions(string $roleId): int
    {
        return DB::table('role_permissions')->where('role_id', $roleId)->delete();
    }

    public function updateRole(string $roleId, array $attributes): void
    {
        RoleRecord::query()->toBase()->where('role_id', $roleId)->update($attributes);
    }

    public function insertRole(array $attributes): void
    {
        try {
            RoleRecord::query()->toBase()->insert($attributes);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw new AuthorizationWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function permissions(?string $moduleCode): array
    {
        return PermissionRecord::query()->toBase()->when($moduleCode !== null, fn($query) => $query->where('module_code', $moduleCode))->orderBy('permission_code')->get(['permission_id', 'permission_code', 'module_code', 'description', 'status'])->all();
    }

    public function lockedUser(string $userId): ?object
    {
        return DB::table('users')->where('user_id', $userId)->lockForUpdate()->first();
    }

    public function lockedAssignment(string $userId, string $assignmentId): ?object
    {
        return AssignmentRecord::query()->toBase()->where(['assignment_id' => $assignmentId, 'user_id' => $userId])->lockForUpdate()->first();
    }

    public function updateAssignment(string $assignmentId, array $attributes): void
    {
        AssignmentRecord::query()->toBase()->where('assignment_id', $assignmentId)->update($attributes);
    }

    public function allEntitlements(): array
    {
        return DB::table('tenant_module_entitlements')->orderBy('hq_id')->orderBy('module_code')->get(['module_code', 'status'])->map(fn($row): array => (array) $row)->all();
    }

    public function tenantUserIds(string $hqId): array
    {
        return DB::table('users')->where('hq_id', $hqId)->pluck('user_id')->all();
    }

    public function scopedActiveNodeIds(string $hqId, bool $all, array $areaIds, array $nodeIds): array
    {
        $query = DB::table('nodes')->where('hq_id', $hqId)->where('status', 'ACTIVE');
        if (!$all) {
            if ($areaIds === [] && $nodeIds === []) {
                return [];
            }
            $query->where(function ($query) use ($areaIds, $nodeIds): void {
                if ($areaIds !== []) {
                    $query->whereIn('area_id', array_values(array_unique($areaIds)));
                }
                if ($nodeIds !== []) {
                    $areaIds === [] ? $query->whereIn('node_id', $nodeIds) : $query->orWhereIn('node_id', $nodeIds);
                }
            });
        }
        return $query->orderBy('node_id')->pluck('node_id')->map(fn($id) => (string) $id)->all();
    }

    public function descendantAreaIds(string $hqId, string $areaId): array
    {
        return array_map(static fn($row): string => (string) $row->area_id, DB::select(<<<'SQL'
        WITH RECURSIVE descendants AS (
            SELECT child_area_id AS area_id FROM area_hierarchies
            WHERE hq_id = ? AND parent_area_id = ?
            UNION ALL
            SELECT h.child_area_id FROM area_hierarchies h
            JOIN descendants d ON h.parent_area_id = d.area_id
            WHERE h.hq_id = ?
        )
        SELECT DISTINCT area_id FROM descendants
        SQL, [$hqId, $areaId, $hqId]));
    }

    public function role(string $roleId): ?object
    {
        return RoleRecord::query()->toBase()->where('role_id', $roleId)->first();
    }

    public function permissionCodesForRole(string $roleId): array
    {
        return DB::table('role_permissions as rp')->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')->where('rp.role_id', $roleId)->orderBy('p.permission_code')->pluck('p.permission_code')->map(fn($code) => (string) $code)->all();
    }

    public function delegableCodes(string $userId, ?string $hqId, bool $tenantWide): array
    {
        return AssignmentRecord::query()->toBase()->from('user_role_assignments as a')->join('roles as r', 'r.role_id', '=', 'a.role_id')->join('role_permissions as rp', 'rp.role_id', '=', 'r.role_id')->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')->leftJoin('tenant_module_entitlements as e', function ($join) use ($hqId): void {
            $join->on('e.module_code', '=', 'p.module_code')->where('e.hq_id', $hqId);
        })->where('a.user_id', $userId)->where('a.hq_id', $hqId)->where(fn($query) => $query->whereNull('r.hq_id')->orWhere('r.hq_id', $hqId))->where('a.status', 'ACTIVE')->where('r.status', 'ACTIVE')->where('p.status', 'ACTIVE')->when($tenantWide, fn($query) => $query->where('e.status', 'ENABLED'))->when($tenantWide, fn($query) => $query->where('a.scope_type', 'TENANT')->whereNull('a.scope_id'))->distinct()->pluck('p.permission_code')->all();
    }

    public function activePermissions(array $permissionCodes): array
    {
        return PermissionRecord::query()->toBase()->whereIn('permission_code', $permissionCodes)->where('status', 'ACTIVE')->get(['permission_id', 'permission_code'])->all();
    }

    public function insertRolePermission(array $attributes): void
    {
        DB::table('role_permissions')->insert($attributes);
    }

    public function activeRole(string $roleId): ?object
    {
        return RoleRecord::query()->toBase()->where('role_id', $roleId)->where('status', 'ACTIVE')->first();
    }

    public function assignmentSlotExists(string $slot): bool
    {
        return AssignmentRecord::query()->toBase()->where('active_slot', $slot)->exists();
    }

    public function insertAssignment(array $attributes): void
    {
        try {
            AssignmentRecord::query()->toBase()->insert($attributes);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw new AuthorizationWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function areaExists(string $hqId, string $scopeId): bool
    {
        return DB::table('areas')->where(['hq_id' => $hqId, 'area_id' => $scopeId])->exists();
    }

    public function nodeExists(string $hqId, string $scopeId): bool
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $scopeId])->exists();
    }

    public function isActiveTenantRole(string $roleId): bool
    {
        return RoleRecord::query()->toBase()->where('role_id', $roleId)->where('status', 'ACTIVE')->where('role_code', '!=', 'platform_super_admin')->exists();
    }

    public function activeAreas(?string $hqId): array
    {
        return DB::table('areas')->where('hq_id', $hqId)->where('status', 'ACTIVE')->orderBy('area_title')->get()->keyBy('area_id')->all();
    }

    public function areaParents(?string $hqId): array
    {
        return DB::table('area_hierarchies')->where('hq_id', $hqId)->pluck('parent_area_id', 'child_area_id')->all();
    }

    public function assignmentNodes(?string $hqId, array $nodeIds): array
    {
        return DB::table('nodes')->where('hq_id', $hqId)->whereIn('node_id', $nodeIds)->orderBy('node_title')->get(['node_id', 'node_title', 'node_code', 'node_type', 'area_id'])->all();
    }

    public function activeRoleUserIds(string $roleId): array
    {
        return AssignmentRecord::query()->toBase()->where('role_id', $roleId)->where('status', 'ACTIVE')->pluck('user_id')->all();
    }
}
