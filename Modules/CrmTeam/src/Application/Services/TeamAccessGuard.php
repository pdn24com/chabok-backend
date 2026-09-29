<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Services;

use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * CRM teams carry their own pair of permissions. The entitlement checked is the customer one,
 * because every crm.* permission of this catalog belongs to the same product area a tenant buys.
 */
final readonly class TeamAccessGuard implements TeamAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertCanRead(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.team.view');
    }

    public function assertCanManage(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.team.manage');
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
