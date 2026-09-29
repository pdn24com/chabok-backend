<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ResolveContext;

use Illuminate\Contracts\Cache\Repository;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\ContextScopeResolverInterface;
use Modules\Authorization\Application\Contracts\RoleNavigationInterface;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Authorization\Application\Repositories\RolePermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\TenantEntitlementRepositoryInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Dto\TenantSummaryDto;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Organization\Application\Repositories\TenantRepositoryInterface;

final readonly class ResolveContextHandler
{
    public function __construct(
        private AuthorizationCacheInvalidatorInterface $authorizationCacheInvalidator,
        private Repository $authorizationCache,
        private ContextScopeResolverInterface $contextScopeResolver,
        private RoleNavigationInterface $roleNavigation,
        private UserRepositoryInterface $userRepository,
        private AssignmentRepositoryInterface $assignmentRepository,
        private TenantEntitlementRepositoryInterface $tenantEntitlementRepository,
        private RolePermissionRepositoryInterface $rolePermissionRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    public function handle(ResolveContextCommand $command): AccessContextDto
    {
        $principal = $command->principal;
        $key = $this->authorizationCacheInvalidator->cacheKey($principal->userId);
        $cached = $this->authorizationCache->get($key);
        if ($cached instanceof AccessContextDto) {
            return $cached;
        }
        $user = $this->userRepository->find($principal->userId);
        if ($user === null || $user->status !== 'ACTIVE' || $user->hq_id !== $principal->hqId) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'common.authentication_required');
        }
        $assignments = $this->assignmentRepository->activeForUserInTenant($principal->userId, $principal->hqId)->all();
        $roleIds = [];
        $roleCodes = [];
        $scopes = [];
        $assignmentsByRole = [];
        $isPlatform = false;
        foreach ($assignments as $assignment) {
            $roleIds[] = $assignment->role_id;
            $roleCodes[] = $assignment->role->role_code;
            $scope = new PermissionScope(ScopeType::from($assignment->scope_type), $assignment->scope_id, (bool) $assignment->includes_descendants);
            if (! in_array($scope, $scopes)) {
                $scopes[] = $scope;
            }
            $assignmentsByRole[$assignment->role_id][] = $scope;
            if ($principal->hqId === null && $assignment->role->role_code === 'platform_super_admin' && $scope->type === ScopeType::PLATFORM && $scope->id === null) {
                $isPlatform = true;
            }
        }
        $roleIds = array_values(array_unique($roleIds));
        $roleCodes = array_values(array_unique($roleCodes));
        sort($roleCodes);
        $entitlements = [];
        $enabledModules = [];
        foreach ($principal->hqId === null ? [] : $this->tenantEntitlementRepository->forTenant($principal->hqId) as $entitlement) {
            $status = EntitlementStatus::from($entitlement->status);
            $entitlements[] = new ModuleEntitlementDto($entitlement->module_code, $status);
            if ($status === EntitlementStatus::ENABLED) {
                $enabledModules[] = strtolower($entitlement->module_code);
            }
        }
        $permissions = [];
        $permissionScopes = [];
        foreach ($roleIds === [] ? [] : $this->rolePermissionRepository->activeGrantsForRoles($roleIds)->all() as $grant) {
            $permission = $grant->permission;
            if ($isPlatform || in_array(strtolower($permission->module_code), $enabledModules, true)) {
                $permissions[] = $permission->permission_code;
            }
            foreach ($assignmentsByRole[$grant->role_id] ?? [] as $scope) {
                $permissionScopes[$permission->permission_code][] = $scope;
            }
        }
        $permissions = array_values(array_unique($permissions));
        sort($permissions);
        $nodes = $principal->hqId === null ? [] : $this->contextScopeResolver->accessibleNodeIds($principal->hqId, $assignments);
        $tenant = $principal->hqId === null ? null : $this->tenantRepository->findIdentity($principal->hqId);
        $context = new AccessContextDto(
            hqId: $principal->hqId,
            tenant: $tenant === null ? null : new TenantSummaryDto($tenant->hq_id, $tenant->hq_code, $tenant->hq_title),
            isPlatformAdmin: $isPlatform,
            roleCodes: $roleCodes,
            permissions: $permissions,
            permissionScopes: $permissionScopes,
            menuKeys: $this->roleNavigation->effective($roleIds),
            scopes: $scopes,
            accessibleNodeIds: $nodes,
            moduleEntitlements: $entitlements,
            defaultNodeId: $nodes[0] ?? null,
        );
        $this->authorizationCache->put($key, $context, 60);

        return $context;
    }
}
