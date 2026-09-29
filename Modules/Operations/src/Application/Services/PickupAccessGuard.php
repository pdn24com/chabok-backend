<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;

final readonly class PickupAccessGuard implements PickupAccessGuardInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
        private PickupTaskRepositoryInterface $pickupTaskRepository,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function access(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if (! $context->isModuleEnabled('Pickup')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        if (! in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }

    public function accessExecution(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $taskId,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if (! $context->isModuleEnabled('Pickup')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! in_array($nodeId, $context->accessibleNodeIds, true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
        $task = $this->pickupTaskRepository->findAtNode($actor->hqId, $nodeId, $taskId);
        if ($task === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $driverUser = $this->driverRepository->userIdOf($actor->hqId, $task->assigned_driver_id);
        if ((string) $driverUser === $actor->userId) {
            return;
        }
        if (! in_array($nodeId, $this->scopedAccess->nodes($context, 'live_operations.intervene'), true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }
}
