<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ResolveContext;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ResolveContextHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationCacheInvalidator $authorizationCacheInvalidator,
        private \Modules\Authorization\Application\Contracts\AuthorizationCache $cache,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\ContextScopeResolver $contextScopeResolver,
        private \Modules\Authorization\Application\RoleNavigation $navigation,
    )
    {
    }

    public function handle(ResolveContextCommand $command): ResolveContextResult
    {
        return new ResolveContextResult($this->execute($command->principal));
    }

    private function execute(AuthenticatedPrincipal $principal): array
    {
        $key = $this->authorizationCacheInvalidator->cacheKey($principal->userId);
        $cached = $this->cache->get($key);
        if (is_string($cached)) {
            $decoded = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists('permission_scopes', $decoded)) {
                return $decoded;
            }
        }
        $user = $this->repository->user($principal->userId);
        if ($user === null || $user->status !== 'ACTIVE' || $user->hq_id !== $principal->hqId) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'Authentication required.');
        }
        $assignments = $this->repository->activeAssignments($principal->userId, $principal->hqId);
        $roleIds = array_values(array_unique(array_column($assignments, 'role_id')));
        $permissionRows = $roleIds === [] ? [] : $this->repository->activeGrants($roleIds);
        $entitlements = $principal->hqId === null ? [] : $this->repository->tenantEntitlements($principal->hqId);
        $enabledModules = array_map(fn($row) => strtolower((string) $row->module_code), array_filter($entitlements, fn($row) => $row->status === 'ENABLED'));
        $isPlatform = $principal->hqId === null && array_filter($assignments, fn($row): bool => $row->role_code === 'platform_super_admin' && $row->scope_type === 'PLATFORM' && $row->scope_id === null) !== [];
        $permissions = array_values(array_unique(array_map(fn($row) => (string) $row->permission_code, array_filter($permissionRows, fn($row): bool => $isPlatform || in_array(strtolower((string) $row->module_code), $enabledModules, true)))));
        sort($permissions);
        $scopes = [];
        foreach ($assignments as $row) {
            $scope = [
                'scope_type' => (string) $row->scope_type,
                'scope_id' => $row->scope_id === null ? null : (string) $row->scope_id,
                'includes_descendants' => (bool) $row->includes_descendants,
            ];
            $scopes[json_encode($scope, JSON_THROW_ON_ERROR)] = $scope;
        }
        $scopes = array_values($scopes);
        $roleCodes = array_values(array_unique(array_map(fn($row) => (string) $row->role_code, $assignments)));
        sort($roleCodes);
        $nodes = $principal->hqId === null ? [] : $this->contextScopeResolver->accessibleNodeIds($principal->hqId, $assignments);
        $tenant = $principal->hqId === null ? null : $this->repository->tenant($principal->hqId);
        $permissionScopes = [];
        foreach ($permissionRows as $grant) {
            foreach (array_filter($assignments, fn($row) => $row->role_id == $grant->role_id) as $assignment) {
                $permissionScopes[$grant->permission_code][] = [
                    'scope_type' => (string) $assignment->scope_type,
                    'scope_id' => $assignment->scope_id,
                    'includes_descendants' => (bool) $assignment->includes_descendants,
                ];
            }
        }
        $context = [
            'hq_id' => $principal->hqId,
            'tenant' => $tenant === null ? null : [
                'hq_id' => (string) $tenant->hq_id,
                'code' => (string) $tenant->hq_code,
                'title' => (string) $tenant->hq_title,
            ],
            'is_platform_admin' => $isPlatform,
            'role_codes' => $roleCodes,
            'permissions' => $permissions,
            'permission_scopes' => $permissionScopes === [] ? new \stdClass() : $permissionScopes,
            'menu_keys' => $this->navigation->effective($roleIds),
            'scopes' => $scopes,
            'accessible_node_ids' => $nodes,
            'module_entitlements' => array_map(fn($row): array => ['module_code' => (string) $row->module_code, 'status' => (string) $row->status], $entitlements),
            'default_node_id' => $nodes[0] ?? null,
        ];
        $this->cache->put($key, 60, json_encode($context, JSON_THROW_ON_ERROR));
        return $context;
    }
}
