<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

/** Persisted roles and grants, with explicit read projections for authorization. */

interface AuthorizationRepository
{
    public function user(string $userId): ?object;

    public function activeAssignments(string $userId, ?string $hqId): array;

    public function activeGrants(array $roleIds): array;

    public function tenantEntitlements(string $hqId): array;

    public function tenant(string $hqId): ?object;

    public function activePermission(string $permissionCode): ?object;

    public function entitlementStatus(string $hqId, string $moduleCode): ?string;

    public function activeNodes(?string $hqId, array $nodeIds): array;

    public function nodeTenant(string $nodeId): ?object;

    public function hasActivePlatformAssignment(string $userId): bool;

    public function visibleRoleIds(?string $hqId): array;

    public function lockedRole(string $roleId): ?object;

    public function removeRolePermissions(string $roleId): int;

    public function updateRole(string $roleId, array $attributes): void;

    public function insertRole(array $attributes): void;

    public function permissions(?string $moduleCode): array;

    public function lockedUser(string $userId): ?object;

    public function lockedAssignment(string $userId, string $assignmentId): ?object;

    public function updateAssignment(string $assignmentId, array $attributes): void;

    public function allEntitlements(): array;

    public function tenantUserIds(string $hqId): array;

    public function scopedActiveNodeIds(string $hqId, bool $all, array $areaIds, array $nodeIds): array;

    public function descendantAreaIds(string $hqId, string $areaId): array;

    public function role(string $roleId): ?object;

    public function permissionCodesForRole(string $roleId): array;

    public function delegableCodes(string $userId, ?string $hqId, bool $tenantWide): array;

    public function activePermissions(array $permissionCodes): array;

    public function insertRolePermission(array $attributes): void;

    public function activeRole(string $roleId): ?object;

    public function assignmentSlotExists(string $slot): bool;

    public function insertAssignment(array $attributes): void;

    public function areaExists(string $hqId, string $scopeId): bool;

    public function nodeExists(string $hqId, string $scopeId): bool;

    public function isActiveTenantRole(string $roleId): bool;

    public function activeAreas(?string $hqId): array;

    public function areaParents(?string $hqId): array;

    public function assignmentNodes(?string $hqId, array $nodeIds): array;

    public function activeRoleUserIds(string $roleId): array;
}
