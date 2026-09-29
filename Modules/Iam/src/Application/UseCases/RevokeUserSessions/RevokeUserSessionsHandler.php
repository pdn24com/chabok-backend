<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\RevokeUserSessions;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class RevokeUserSessionsHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private ConnectionInterface $connection,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(RevokeUserSessionsCommand $command): int
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');

        return $this->connection->transaction(function () use ($actor, $userId, $hqId, $correlationId): int {
            $user = $this->userRepository->lockByTenant($hqId, $userId);
            $this->userAccessGuard->assertTenantUser($user !== null, $user?->hq_id, $hqId);
            $count = $this->sessionLifecycle->revokeUserSessions($userId, 'ADMIN_REVOKED');
            $this->auditWriter->write($hqId, $actor->userId, 'USER_SESSIONS_REVOKED', 'USER', $userId, $correlationId, safeNote: "{$count} sessions revoked.");

            return $count;
        }, attempts: 3);
    }
}
