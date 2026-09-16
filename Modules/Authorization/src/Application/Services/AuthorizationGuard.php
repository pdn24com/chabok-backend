<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Authorization\Application\AuthorizationCatalog;

final readonly class AuthorizationGuard
{
    public function __construct(
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler $resolveContext,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
    )
    {
    }

    public function assertPermission(AuthenticatedPrincipal $actor, string $permissionCode, ?string $expectedHqId = null): void
    {
        if ($expectedHqId !== null && $actor->hqId !== $expectedHqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $permission = $this->repository->activePermission($permissionCode);
        if ($permission === null) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if ($actor->hqId !== null) {
            $status = $this->repository->entitlementStatus($actor->hqId, $permission->module_code);
            if ($status !== 'ENABLED') {
                throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
            }
        }
        if (!in_array($permissionCode, $this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($actor))->data['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }

    public function assertNodeAccessible(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        $node = $this->repository->nodeTenant($nodeId);
        if ($node !== null && $node->hq_id !== $actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $this->assertPermission($actor, 'node_context.switch', $actor->hqId);
        if (!in_array($nodeId, $this->scopedAccess->nodes($this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($actor))->data, 'node_context.switch'), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return $this->repository->hasActivePlatformAssignment($userId);
    }

    public function tenantId(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        return $actor->hqId;
    }

    public function assertVisibleRole(string $roleId, ?string $hqId): void
    {
        $role = $this->repository->role($roleId);
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($role->hq_id !== null && $role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
    }

    public function assertMutableRole(?object $role, string $hqId): void
    {
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($role->role_kind !== 'CUSTOM') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'System and template roles are immutable.');
        }
        if ($role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
    }

    public function assertDelegablePermissions(AuthenticatedPrincipal $actor, array $permissionCodes, bool $tenantWide = false): void
    {
        $approved = array_keys(AuthorizationCatalog::permissions());
        if (array_diff($permissionCodes, $approved) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An unknown permission was supplied.');
        }
        if (array_diff($permissionCodes, $this->delegableCodes($actor, $tenantWide)) !== []) {
            throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'Access denied.');
        }
    }

    public function delegableCodes(AuthenticatedPrincipal $actor, bool $tenantWide = false): array
    {
        return $this->repository->delegableCodes($actor->userId, $actor->hqId, $tenantWide);
    }

    public function assertRoleReadAccess(AuthenticatedPrincipal $actor): void
    {
        $hqId = $this->tenantId($actor);
        $code = in_array('iam.roles.manage', $this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($actor))->data['permissions'], true) ? 'iam.roles.manage' : 'iam.roles.assign';
        $this->assertPermission($actor, $code, $hqId);
    }

    public function assertRoleManagement(AuthenticatedPrincipal $actor): void
    {
        $this->assertPermission($actor, 'iam.roles.manage', $this->tenantId($actor));
        if (!in_array('iam.roles.manage', $this->delegableCodes($actor, true), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function assertScopeTarget(string $hqId, string $userId, string $scopeType, ?string $scopeId, bool $includesDescendants): void
    {
        if ($scopeType === 'TENANT') {
            if ($scopeId !== null || $includesDescendants) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Tenant scope cannot have a target or descendants flag.');
            }
            return;
        }
        if ($scopeType === 'SELF') {
            if ($scopeId !== $userId || $includesDescendants) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Self scope must target the assigned user.');
            }
            return;
        }
        if ($scopeType === 'AREA') {
            if ($scopeId === null || !$this->repository->areaExists($hqId, $scopeId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The area scope is invalid.');
            }
            return;
        }
        if ($scopeType === 'NODE') {
            if ($scopeId === null || $includesDescendants || !$this->repository->nodeExists($hqId, $scopeId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The node scope is invalid.');
            }
            return;
        }
        if (in_array($scopeType, ['VENDOR', 'VENDOR_BRANCH'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The scope target is not available in Stage 0.');
        }
        throw new ApiException(ApiErrorCode::ValidationError, 422, 'The scope type is invalid.');
    }

    public function assertScopeDelegable(
        AuthenticatedPrincipal $actor,
        string $scopeType,
        ?string $scopeId,
        bool $includesDescendants = false,
        string $permission = 'iam.roles.assign',
    ): void
    {
        $scopes = \Modules\Foundation\Application\ScopedAccess::scopes($this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($actor))->data, $permission);
        if (!$this->scopedAccess->covers($scopes, (string) $actor->hqId, $scopeType, $scopeId, $includesDescendants)) {
            throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'Access denied.');
        }
    }
}
