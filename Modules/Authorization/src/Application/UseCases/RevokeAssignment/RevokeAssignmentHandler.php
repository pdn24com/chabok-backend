<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\RevokeAssignment;

use Illuminate\Database\ConnectionInterface;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;

final readonly class RevokeAssignmentHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ConnectionInterface $connection,
        private RoleReaderInterface $roleReader,
        private ClockInterface $clock,
        private AuthorizationCacheInvalidatorInterface $authorizationCacheInvalidator,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private AssignmentRepositoryInterface $assignmentRepository,
    ) {}

    public function handle(RevokeAssignmentCommand $command): void
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $assignmentId = $command->assignmentId;
        $correlationId = $command->correlationId;
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertPermission($actor, 'iam.roles.assign', $hqId);
        $this->connection->transaction(function () use ($actor, $userId, $assignmentId, $correlationId, $hqId): void {
            $assignment = $this->assignmentRepository->lockForUser($assignmentId, $userId);
            if ($assignment === null || $assignment->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
            }
            if ($assignment->status !== 'ACTIVE') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.assignment_is_not_active');
            }
            $codes = $this->roleReader->permissionCodesForRole($assignment->role_id);
            $this->authorizationGuard->assertDelegablePermissions($actor, $codes);
            $this->authorizationGuard->assertScopeDelegable($actor, new PermissionScope(ScopeType::from($assignment->scope_type), $assignment->scope_id, (bool) $assignment->includes_descendants), ['iam.roles.assign', ...$codes]);
            $this->assignmentRepository->update($assignmentId, [
                'status' => 'REVOKED',
                'active_slot' => null,
                'revoked_by' => $actor->userId,
                'revoked_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->authorizationCacheInvalidator->invalidateUser($userId);
            $this->auditWriter->write($hqId, $actor->userId, 'ROLE_ASSIGNMENT_REVOKED', 'ASSIGNMENT', $assignmentId, $correlationId);
            $this->outboxWriter->write($hqId, 'USER', $userId, 'iam.user.assignments_changed', $correlationId, ['user_id' => $userId]);
        }, attempts: 3);

    }
}
