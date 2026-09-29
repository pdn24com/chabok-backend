<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class FleetAccessGuard implements FleetAccessGuardInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
        private NodeRepositoryInterface $nodeRepository,
        private DriverRepositoryInterface $driverRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function access(AuthenticatedPrincipal $actor, string $permission): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if (! $context->isModuleEnabled('Driver')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }

    public function scopeNodes(AuthenticatedPrincipal $actor, string $permission): array
    {
        return $this->scopedAccess->nodes($this->accessContextResolver->resolve($actor), $permission, false);
    }

    public function assertScopeNode(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): void {
        if (! in_array($nodeId, $this->scopeNodes($actor, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }

    public function activeNode(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        if (! $this->nodeRepository->activeExists((string) $actor->hqId, $nodeId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.home_node_must_be_active_belong_current', ['home_node_id' => ['operations.home_node_is_invalid_or_inactive']]);
        }
    }

    public function availableUser(
        AuthenticatedPrincipal $actor,
        ?string $userId,
        ?string $currentDriverId = null,
    ): void {
        if ($userId === null || $userId === '') {
            return;
        }
        if (! $this->userRepository->existsInTenant((string) $actor->hqId, $userId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.iam_user_must_belong_current_hq', ['user_id' => ['operations.selected_user_does_not_belong_to_hq']]);
        }
        if ($this->driverRepository->linkedToAnotherDriver($userId, $currentDriverId)) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.iam_user_is_already_assigned_another_driver');
        }
    }
}
