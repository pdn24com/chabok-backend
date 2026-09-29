<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateAssignments;

use Illuminate\Database\ConnectionInterface;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\TenantAssignmentWriterInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class CreateAssignmentsHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ConnectionInterface $connection,
        private TenantAssignmentWriterInterface $tenantAssignmentWriter,
        private AuthorizationCacheInvalidatorInterface $authorizationCacheInvalidator,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    /** @return list<AssignmentRecord> */
    public function handle(CreateAssignmentsCommand $command): array
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $assignments = $command->assignments;
        $correlationId = $command->correlationId;
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertPermission($actor, 'iam.roles.assign', $hqId);

        return $this->connection->transaction(function () use ($actor, $userId, $assignments, $correlationId, $hqId): array {
            $user = $this->userRepository->lock($userId);
            if ($user === null || $user->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
            }
            $created = $this->tenantAssignmentWriter->createTenantAssignments($actor, $userId, $hqId, $assignments);
            $this->authorizationCacheInvalidator->invalidateUser($userId);
            $this->auditWriter->write($hqId, $actor->userId, 'ROLE_ASSIGNMENTS_CREATED', 'USER', $userId, $correlationId, after: ['assignment_ids' => array_map(fn (AssignmentRecord $assignment): string => $assignment->assignment_id, $created)]);
            $this->outboxWriter->write($hqId, 'USER', $userId, 'iam.user.assignments_changed', $correlationId, ['user_id' => $userId]);

            return $created;
        }, attempts: 3);
    }
}
