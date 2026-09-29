<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Services;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CustomerAccessGuard implements CustomerAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertCanCreate(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'customer.create');
    }

    public function assertCanRead(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'customer.view');
    }

    public function assertCanEdit(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'customer.edit');
    }

    public function assertCanReadFinance(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.finance.view');
    }

    public function assertCanEditFinance(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.finance.manage');
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
