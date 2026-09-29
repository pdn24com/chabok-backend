<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Authorization\Application\Catalogs\AuthorizationCatalog;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Dto\DelegationSnapshotDto;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Authorization\Application\Repositories\PermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Application\Repositories\TenantEntitlementRepositoryInterface;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Authorization\Domain\Enums\AssignmentScopeFailure;
use Modules\Authorization\Domain\Policies\AssignmentScopePolicy;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Services\ScopedAccess;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class AuthorizationGuard implements AuthorizationGuardInterface
{
    public function __construct(
        private ResolveContextHandler $resolveContextHandler,
        private ScopedAccessInterface $scopedAccess,
        private AssignmentScopePolicy $assignmentScopePolicy,
        private NodeRepositoryInterface $nodeRepository,
        private PermissionRepositoryInterface $permissionRepository,
        private TenantEntitlementRepositoryInterface $tenantEntitlementRepository,
        private AssignmentRepositoryInterface $assignmentRepository,
        private RoleRepositoryInterface $roleRepository,
    ) {}

    public function assertPermission(
        AuthenticatedPrincipal $actor,
        string $permissionCode,
        ?string $expectedHqId = null,
    ): void {
        if ($expectedHqId !== null && $actor->hqId !== $expectedHqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $permission = $this->permissionRepository->findActiveByCode($permissionCode);
        if ($permission === null) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        if ($actor->hqId !== null) {
            $status = $this->tenantEntitlementRepository->statusFor($actor->hqId, (string) $permission->module_code);
            if ($status !== 'ENABLED') {
                throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
            }
        }
        if (! in_array($permissionCode, $this->resolveContextHandler->handle(new ResolveContextCommand($actor))->permissions, true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }

    public function assertNodeAccessible(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        $owner = $this->nodeRepository->tenantOf($nodeId);
        if ($owner !== null && $owner !== $actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $this->assertPermission($actor, 'node_context.switch', $actor->hqId);
        if (! in_array($nodeId, $this->scopedAccess->nodes($this->resolveContextHandler->handle(new ResolveContextCommand($actor)), 'node_context.switch'), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return $this->assignmentRepository->hasPlatformSuperAdmin($userId);
    }

    public function tenantId(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }

        return $actor->hqId;
    }

    public function assertVisibleRole(string $roleId, ?string $hqId): void
    {
        $role = $this->roleRepository->find($roleId);
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        if ($role->hq_id !== null && $role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
    }

    public function assertMutableRole(?RoleRecord $role, string $hqId): void
    {
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        if ($role->role_kind !== 'CUSTOM') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.system_template_roles_are_immutable');
        }
        if ($role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
    }

    public function assertDelegablePermissions(
        AuthenticatedPrincipal $actor,
        array $permissionCodes,
        bool $tenantWide = false,
    ): void {
        $this->assertPermissionCodesDelegable($permissionCodes, $this->delegableCodes($actor, $tenantWide));
    }

    /** @param list<string> $permissionCodes @param list<string> $delegableCodes */
    public function assertPermissionCodesDelegable(array $permissionCodes, array $delegableCodes): void
    {
        if (array_diff($permissionCodes, array_keys(AuthorizationCatalog::permissions())) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.unknown_permission_supplied');
        }
        if (array_diff($permissionCodes, $delegableCodes) !== []) {
            throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'common.access_denied');
        }
    }

    public function delegationSnapshot(AuthenticatedPrincipal $actor): DelegationSnapshotDto
    {
        return new DelegationSnapshotDto($this->delegableCodes($actor),
            $this->resolveContextHandler->handle(new ResolveContextCommand($actor)), $this->scopedAccess->coverage($actor->hqId));
    }

    /** @param list<string> $permissions */
    public function assertSnapshotScope(DelegationSnapshotDto $snapshot, PermissionScope $scope, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (! $snapshot->coverage->covers(ScopedAccess::scopes($snapshot->context, $permission), $scope->type, $scope->id, $scope->includesDescendants)) {
                throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'common.access_denied');
            }
        }
    }

    public function delegableCodes(AuthenticatedPrincipal $actor, bool $tenantWide = false): array
    {
        return $this->permissionRepository->delegableCodes($actor, $tenantWide);
    }

    public function assertRoleReadAccess(AuthenticatedPrincipal $actor): void
    {
        $hqId = $this->tenantId($actor);
        $code = in_array('iam.roles.manage', $this->resolveContextHandler->handle(new ResolveContextCommand($actor))->permissions, true) ? 'iam.roles.manage' : 'iam.roles.assign';
        $this->assertPermission($actor, $code, $hqId);
    }

    public function assertRoleManagement(AuthenticatedPrincipal $actor): void
    {
        $this->assertPermission($actor, 'iam.roles.manage', $this->tenantId($actor));
        if (! in_array('iam.roles.manage', $this->delegableCodes($actor, true), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }

    public function assertScopeTarget(
        string $userId,
        PermissionScope $scope,
        bool $targetExists,
    ): void {
        $failure = $this->assignmentScopePolicy->validate($userId, $scope, $targetExists);
        if ($failure === null) {
            return;
        }
        $messageKey = match ($failure) {
            AssignmentScopeFailure::TenantTarget => 'authorization.tenant_scope_cannot_have_target',
            AssignmentScopeFailure::SelfTarget => 'authorization.self_scope_must_target_assigned_user',
            AssignmentScopeFailure::AreaTarget => 'authorization.area_scope_is_invalid',
            AssignmentScopeFailure::NodeTarget => 'authorization.node_scope_is_invalid',
            AssignmentScopeFailure::UnsupportedTarget => 'authorization.scope_target_is_not_available',
            AssignmentScopeFailure::InvalidType => 'authorization.scope_type_is_invalid',
        };
        throw new ApiException(ApiErrorCode::ValidationError, 422, $messageKey);
    }

    public function assertScopeDelegable(
        AuthenticatedPrincipal $actor,
        PermissionScope $scope,
        array $permissions = ['iam.roles.assign'],
    ): void {
        $context = $this->resolveContextHandler->handle(new ResolveContextCommand($actor));
        $coverage = $this->scopedAccess->coverage($actor->hqId);
        foreach ($permissions as $permission) {
            $scopes = ScopedAccess::scopes($context, $permission);
            if (! $coverage->covers($scopes, $scope->type, $scope->id, $scope->includesDescendants)) {
                throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'common.access_denied');
            }
        }
    }
}
