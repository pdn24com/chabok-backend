<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CatalogRecordAccessGuard
{
    public function __construct(private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization)
    {
    }

    public function authorize(AuthenticatedPrincipal $actor, bool $write = false): void
    {
        $context = $this->authorization->resolve($actor);
        if ($actor->hqId === null || !array_filter($context['module_entitlements'], fn($e) => $e['module_code'] === 'ServiceCatalog' && $e['status'] === 'ENABLED')) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        // Immediate changes require both existing editing and effective-publication grants.
        foreach ($write ? ['service_catalog.view', 'service_catalog.manage_draft', 'service_catalog.publish'] : ['service_catalog.view'] as $permission) {
            if (!in_array($permission, $context['permissions'], true)) {
                throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
            }
        }
    }
}
