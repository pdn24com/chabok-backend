<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Services;

use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class TaskAccessGuard implements TaskAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertCanRead(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.task.view');
    }

    public function assertCanManage(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.task.manage');
    }

    public function assertCanRecordActivity(AuthenticatedPrincipal $actor): string
    {
        return $this->assertTenantPermission($actor, 'crm.activity.manage');
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
