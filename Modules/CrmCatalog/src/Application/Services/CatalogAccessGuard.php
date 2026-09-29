<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Services;

use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CatalogAccessGuard implements CatalogAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertCanRead(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.catalog.view');
    }

    public function assertCanManage(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.catalog.manage');
    }

    private function assertTenantPermission(AuthenticatedPrincipal $actor, string $permission): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if ($context->hqId !== $actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        // The catalog is part of the commercial CRM, so it rides on the CRM entitlement the tenant already holds.
        if (! $context->isModuleEnabled('Customer')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        foreach ($context->scopesFor($permission) as $scope) {
            if ($scope->type === ScopeType::TENANT) {
                return $actor->hqId;
            }
        }

        throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
    }
}
