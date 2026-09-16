<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PickupAccessGuard
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks,
    )
    {
    }

    public function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        if (array_filter($context['module_entitlements'], fn($e) => $e['module_code'] === 'Pickup' && $e['status'] === 'ENABLED') === []) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if (!in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function accessExecution(AuthenticatedPrincipal $actor, string $nodeId, string $taskId): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        if (array_filter($context['module_entitlements'], fn($entry) => $entry['module_code'] === 'Pickup' && $entry['status'] === 'ENABLED') === []) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($nodeId, $context['accessible_node_ids'], true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
        $task = $this->tasks->find($actor->hqId, $nodeId, $taskId);
        if ($task === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $driverUser = $this->tasks->driverUser($actor->hqId, $task->assigned_driver_id);
        if ((string) $driverUser === $actor->userId) {
            return;
        }
        if (!in_array($nodeId, $this->scopedAccess->nodes($context, 'live_operations.intervene'), true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }
}
