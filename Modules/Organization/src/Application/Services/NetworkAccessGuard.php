<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class NetworkAccessGuard
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
    )
    {
    }

    public function scopeAreas(AuthenticatedPrincipal $actor, string $permission): array
    {
        $scopes = \Modules\Foundation\Application\ScopedAccess::scopes($this->authorization->resolve($actor), $permission);
        return array_values(array_filter($this->network->areaIds($actor->hqId), fn($id) => $this->scopedAccess->covers($scopes, $actor->hqId, 'AREA', $id)));
    }

    public function assertAreaScope(AuthenticatedPrincipal $actor, string $permission, ?string $areaId, bool $descendants = false): void
    {
        $scopes = \Modules\Foundation\Application\ScopedAccess::scopes($this->authorization->resolve($actor), $permission);
        if (!$this->scopedAccess->covers($scopes, $actor->hqId, $areaId === null ? 'TENANT' : 'AREA', $areaId, $descendants)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function assertNodeScope(AuthenticatedPrincipal $actor, string $permission, string $nodeId): void
    {
        if (!in_array($nodeId, $this->scopedAccess->nodes($this->authorization->resolve($actor), $permission, false), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function access(AuthenticatedPrincipal $actor, string $permission): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        if (array_filter($context['module_entitlements'] ?? [], fn($e) => ($e['module_code'] ?? null) === 'LiveOperations' && ($e['status'] ?? null) === 'ENABLED') === []) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'] ?? [], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        return $actor->hqId;
    }
}
