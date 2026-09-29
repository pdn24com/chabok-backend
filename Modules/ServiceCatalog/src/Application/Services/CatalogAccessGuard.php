<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;

final readonly class CatalogAccessGuard implements CatalogAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertAccess(
        AuthenticatedPrincipal $actor,
        string $permission,
        bool $runtime = false,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        $entitled = $context->hasAnyEnabledModule($runtime ? ['ServiceCatalog', 'Consignment'] : ['ServiceCatalog']);
        if (! $entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }

    public function authorizeRecord(AuthenticatedPrincipal $actor, bool $write = false): void
    {
        $context = $this->accessContextResolver->resolve($actor);
        if ($actor->hqId === null || ! $context->isModuleEnabled('ServiceCatalog')) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        // Immediate changes require both existing editing and effective-publication grants.
        foreach ($write ? ['service_catalog.view', 'service_catalog.manage_draft', 'service_catalog.publish'] : ['service_catalog.view'] as $permission) {
            if (! $context->hasPermission($permission)) {
                throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
            }
        }
    }
}
