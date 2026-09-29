<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

use Modules\Authorization\Application\Dto\DelegationSnapshotDto;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;

interface AuthorizationGuardInterface
{
    public function assertPermission(AuthenticatedPrincipal $actor, string $permissionCode, ?string $expectedHqId = null): void;

    public function assertNodeAccessible(AuthenticatedPrincipal $actor, string $nodeId): void;

    public function hasActivePlatformAssignment(string $userId): bool;

    public function tenantId(AuthenticatedPrincipal $actor): string;

    public function assertVisibleRole(string $roleId, ?string $hqId): void;

    public function assertMutableRole(?RoleRecord $role, string $hqId): void;

    public function assertDelegablePermissions(AuthenticatedPrincipal $actor, array $permissionCodes, bool $tenantWide = false): void;

    /** @param list<string> $permissionCodes @param list<string> $delegableCodes */
    public function assertPermissionCodesDelegable(array $permissionCodes, array $delegableCodes): void;

    public function delegationSnapshot(AuthenticatedPrincipal $actor): DelegationSnapshotDto;

    /** @param list<string> $permissions */
    public function assertSnapshotScope(DelegationSnapshotDto $snapshot, PermissionScope $scope, array $permissions): void;

    public function delegableCodes(AuthenticatedPrincipal $actor, bool $tenantWide = false): array;

    public function assertRoleReadAccess(AuthenticatedPrincipal $actor): void;

    public function assertRoleManagement(AuthenticatedPrincipal $actor): void;

    public function assertScopeTarget(string $userId, PermissionScope $scope, bool $targetExists): void;

    public function assertScopeDelegable(AuthenticatedPrincipal $actor, PermissionScope $scope, array $permissions = ['iam.roles.assign']): void;
}
