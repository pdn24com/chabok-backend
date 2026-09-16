<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Authorization\Domain\AuthorizationWriteConflict;

final readonly class TenantAssignmentWriter
{
    public function __construct(
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function createTenantAssignment(AuthenticatedPrincipal $actor, string $userId, string $hqId, array $input): array
    {
        $role = $this->repository->activeRole($input['role_id']);
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The role is invalid.');
        }
        if ($role->hq_id !== null && $role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        if ($role->role_code === 'platform_super_admin' || $input['scope_type'] === 'PLATFORM') {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $permissionCodes = $this->roleReader->permissionCodesForRole((string) $role->role_id);
        $this->authorizationGuard->assertDelegablePermissions($actor, $permissionCodes);
        $scopeId = $input['scope_id'] ?? null;
        $this->authorizationGuard->assertScopeTarget($hqId, $userId, (string) $input['scope_type'], $scopeId, (bool) $input['includes_descendants']);
        $this->authorizationGuard->assertScopeDelegable($actor, (string) $input['scope_type'], $scopeId, (bool) $input['includes_descendants']);
        foreach ($permissionCodes as $permission) {
            $this->authorizationGuard->assertScopeDelegable($actor, (string) $input['scope_type'], $scopeId, (bool) $input['includes_descendants'], $permission);
        }
        $slot = hash('sha256', implode('|', [$userId, (string) $role->role_id, (string) $input['scope_type'], (string) ($scopeId ?? '-')]));
        if ($this->repository->assignmentSlotExists($slot)) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'The assignment already exists.');
        }
        $assignmentId = $this->identifiers->uuid();
        try {
            $this->repository->insertAssignment([
                'assignment_id' => $assignmentId,
                'hq_id' => $hqId,
                'user_id' => $userId,
                'role_id' => $role->role_id,
                'scope_type' => $input['scope_type'],
                'scope_id' => $scopeId,
                'includes_descendants' => (bool) $input['includes_descendants'],
                'status' => 'ACTIVE',
                'active_slot' => $slot,
                'assigned_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        } catch (AuthorizationWriteConflict $exception) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'The assignment already exists.');
        }
        return [
            'assignment_id' => $assignmentId,
            'user_id' => $userId,
            'role_id' => (string) $role->role_id,
            'scope_type' => (string) $input['scope_type'],
            'scope_id' => $scopeId,
            'includes_descendants' => (bool) $input['includes_descendants'],
            'status' => 'ACTIVE',
        ];
    }
}
