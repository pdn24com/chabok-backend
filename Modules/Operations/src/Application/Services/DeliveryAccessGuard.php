<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class DeliveryAccessGuard
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
    )
    {
    }

    public function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        if (array_filter($context['module_entitlements'], fn($entry) => $entry['module_code'] === 'LiveOperations' && $entry['status'] === 'ENABLED') === []) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if (!in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function executionAccess(AuthenticatedPrincipal $actor, string $nodeId, string $id): void
    {
        $task = $this->tasks->find($actor->hqId, $nodeId, $id);
        if ($task === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $driverUser = $this->tasks->driverUser($actor->hqId, $task->assigned_driver_id);
        if ((string) $driverUser === $actor->userId) {
            return;
        }
        $this->access($actor, $nodeId, 'live_operations.intervene');
    }
}
