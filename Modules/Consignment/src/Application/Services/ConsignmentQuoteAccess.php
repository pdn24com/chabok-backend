<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ConsignmentQuoteAccess
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
    )
    {
    }

    public function assertAccess(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        $entitled = array_filter($context['module_entitlements'], fn(array $item): bool => $item['module_code'] === 'Consignment' && $item['status'] === 'ENABLED') !== [];
        if (!$entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if (!in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }
}
