<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Services;

use Modules\CrmCatalog\Application\Contracts\IndustryAccessGuardInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class IndustryAccessGuard implements IndustryAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertCanList(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if ($context->hqId !== $actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        // The industry knowledge base is shared by the customer file and the general catalog item, so it
        // rides on the CRM entitlement the tenant already holds rather than on one of its own.
        if (! $context->isModuleEnabled('Customer')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission('crm.industry.view')) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        foreach ($context->scopesFor('crm.industry.view') as $scope) {
            if ($scope->type === ScopeType::TENANT) {
                return $actor->hqId;
            }
        }

        throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
    }
}
