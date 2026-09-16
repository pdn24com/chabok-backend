<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class FleetAccessGuard
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
    )
    {
    }

    public function access(AuthenticatedPrincipal $actor, string $permission): void
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        if (array_filter($context['module_entitlements'], fn($entry) => $entry['module_code'] === 'Driver' && $entry['status'] === 'ENABLED') === []) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }

    public function scopeNodes(AuthenticatedPrincipal $actor, string $permission): array
    {
        return $this->scopedAccess->nodes($this->authorization->resolve($actor), $permission, false);
    }

    public function assertScopeNode(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if (!in_array($nodeId, $this->scopeNodes($actor, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function activeNode(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        if (!$this->fleet->activeNodeExists($actor->hqId, $nodeId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Home node must be active and belong to the current HQ.', ['home_node_id' => ['گره مبنا معتبر و فعال نیست.']]);
        }
    }

    public function availableUser(AuthenticatedPrincipal $actor, mixed $userId, ?string $currentDriverId = null): void
    {
        if ($userId === null || $userId === '') {
            return;
        }
        if (!$this->fleet->tenantUserExists($actor->hqId, $userId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'IAM user must belong to the current HQ.', ['user_id' => ['کاربر انتخاب‌شده متعلق به این سازمان نیست.']]);
        }
        if ($this->fleet->userHasDriver($userId, $currentDriverId)) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'The IAM user is already assigned to another Driver.');
        }
    }
}
