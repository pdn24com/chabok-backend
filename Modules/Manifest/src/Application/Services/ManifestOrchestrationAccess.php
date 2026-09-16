<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestOrchestrationAccess
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
    )
    {
    }

    public function reviewAccess(AuthenticatedPrincipal $actor, string $node): void
    {
        $this->access($actor, $node, 'manifest.approve');
        $this->requirePermission($actor, 'live_operations.intervene');
    }

    public function requirePermission(AuthenticatedPrincipal $actor, string $permission): void
    {
        $c = $this->authorization->resolve($actor);
        if (!in_array($permission, $c['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }

    public function access(AuthenticatedPrincipal $actor, string $node, string $permission): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $c = $this->authorization->resolve($actor);
        if (array_filter($c['module_entitlements'], fn($e) => $e['module_code'] === 'Manifest' && $e['status'] === 'ENABLED') === []) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $c['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if (!in_array($node, $this->scopedAccess->nodes($c, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }
}
