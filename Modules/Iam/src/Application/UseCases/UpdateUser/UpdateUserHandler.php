<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\UpdateUser;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Dto\UserAuditSnapshotDto;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class UpdateUserHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private ConnectionInterface $connection,
        private AuditWriterInterface $auditWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(UpdateUserCommand $command): UserRecord
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');

        return $this->connection->transaction(function () use ($actor, $userId, $input, $hqId, $correlationId): UserRecord {
            $before = $this->userRepository->lockByTenant($hqId, $userId);
            $this->userAccessGuard->assertTenantUser($before !== null, $before?->hq_id, $hqId);
            $beforeSnapshot = UserAuditSnapshotDto::fromUser($before);
            $this->userRepository->apply($before, $input->attributes());
            $after = $before;
            $this->auditWriter->write($hqId, $actor->userId, 'USER_PROFILE_UPDATED', 'USER', $userId, $correlationId, $beforeSnapshot, UserAuditSnapshotDto::fromUser($after));

            return $after;
        }, attempts: 3);
    }
}
