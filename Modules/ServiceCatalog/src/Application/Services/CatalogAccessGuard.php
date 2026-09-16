<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CatalogAccessGuard
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
        $modules = $context['module_entitlements'];
        $entitled = (bool) array_filter($modules, fn($e) => in_array($e['module_code'], $runtime ? ['ServiceCatalog', 'Consignment'] : ['ServiceCatalog'], true) && $e['status'] === 'ENABLED');
        if (!$entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }
}
