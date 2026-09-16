<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ScheduleAccessGuard
{
    public function __construct(private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization)
    {
    }

    public function assertAccess(AuthenticatedPrincipal $actor, string $permission, bool $runtime = false): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        if (!array_filter($context['module_entitlements'], fn($item) => in_array($item['module_code'], $runtime ? ['ServiceCatalog', 'Consignment'] : ['ServiceCatalog'], true) && $item['status'] === 'ENABLED')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }
}
