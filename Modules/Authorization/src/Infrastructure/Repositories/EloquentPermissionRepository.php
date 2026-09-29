<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Modules\Authorization\Application\Repositories\PermissionRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\TenantEntitlementRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    /** Columns the permission catalogue endpoint returns. */
    private const CATALOGUE_COLUMNS = ['permission_id', 'permission_code', 'module_code', 'description', 'status'];

    public function findActiveByCode(string $permissionCode): ?PermissionRecord
    {
        return PermissionRecord::query()
            ->where('permission_code', $permissionCode)
            ->where('status', 'ACTIVE')
            ->first();
    }

    public function activeByCodes(array $permissionCodes): array
    {
        return PermissionRecord::query()
            ->whereIn('permission_code', $permissionCodes)
            ->where('status', 'ACTIVE')
            ->get(['permission_id', 'permission_code'])
            ->all();
    }

    public function catalogue(?string $moduleCode): array
    {
        return PermissionRecord::query()
            ->when($moduleCode !== null, fn ($query) => $query->where('module_code', $moduleCode))
            ->orderBy('permission_code')
            ->get(self::CATALOGUE_COLUMNS)
            ->all();
    }

    public function codesForRole(string $roleId): array
    {
        return PermissionRecord::query()->whereHas('roles', fn ($role) => $role->where('roles.role_id', $roleId))
            ->orderBy('permission_code')->pluck('permission_code')->all();
    }

    public function delegableCodes(AuthenticatedPrincipal $actor, bool $tenantWide): array
    {
        $permissions = PermissionRecord::query()->where('status', 'ACTIVE')->whereHas('roles', function ($roles) use ($actor, $tenantWide): void {
            $roles->where('roles.status', 'ACTIVE')->where(fn ($owner) => $owner->whereNull('roles.hq_id')->orWhere('roles.hq_id', $actor->hqId))->whereHas('assignments', function ($assignments) use ($actor, $tenantWide): void {
                $assignments->where('user_id', $actor->userId)->where('hq_id', $actor->hqId)->where('status', 'ACTIVE');
                if ($tenantWide) {
                    $assignments->where('scope_type', 'TENANT')->whereNull('scope_id');
                }
            });
        });
        if ($tenantWide) {
            $permissions->whereIn('module_code', TenantEntitlementRecord::query()->select('module_code')->where('hq_id', $actor->hqId)->where('status', 'ENABLED'));
        }

        return $permissions->pluck('permission_code')->all();
    }
}
