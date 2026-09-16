<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\RevokeAssignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RevokeAssignmentHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Authorization\Application\Services\AuthorizationCacheInvalidator $authorizationCacheInvalidator,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(RevokeAssignmentCommand $command): RevokeAssignmentResult
    {
        $this->execute($command->actor, $command->userId, $command->assignmentId, $command->correlationId);
        return new RevokeAssignmentResult();
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, string $assignmentId, string $correlationId): void
    {
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertPermission($actor, 'iam.roles.assign', $hqId);
        $this->transactions->run(function () use ($actor, $userId, $assignmentId, $correlationId, $hqId): void {
            $assignment = $this->repository->lockedAssignment($userId, $assignmentId);
            if ($assignment === null || $assignment->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            if ($assignment->status !== 'ACTIVE') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The assignment is not active.');
            }
            $this->authorizationGuard->assertScopeDelegable($actor, (string) $assignment->scope_type, $assignment->scope_id, (bool) $assignment->includes_descendants);
            $codes = $this->roleReader->permissionCodesForRole((string) $assignment->role_id);
            $this->authorizationGuard->assertDelegablePermissions($actor, $codes);
            foreach ($codes as $code) {
                $this->authorizationGuard->assertScopeDelegable($actor, (string) $assignment->scope_type, $assignment->scope_id, (bool) $assignment->includes_descendants, $code);
            }
            $this->repository->updateAssignment($assignmentId, [
                'status' => 'REVOKED',
                'active_slot' => null,
                'revoked_by' => $actor->userId,
                'revoked_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->authorizationCacheInvalidator->invalidateUser($userId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_ASSIGNMENT_REVOKED', 'ASSIGNMENT', $assignmentId, $correlationId);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.assignments_changed', $correlationId, ['user_id' => $userId]);
        });
    }
}
