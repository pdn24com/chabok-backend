<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Services;

use Modules\CrmSales\Application\Contracts\ContractAccessGuardInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * Contracts carry their own pair of permissions, apart from the sales documents they may be built on:
 * seeing a quote is not the same grant as seeing what the customer signed. The entitlement checked is
 * the customer one, because every crm.* permission of this catalog belongs to the same product area.
 */
final readonly class ContractAccessGuard implements ContractAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertCanRead(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.contract.view');
    }

    public function assertCanEdit(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.contract.manage');
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
