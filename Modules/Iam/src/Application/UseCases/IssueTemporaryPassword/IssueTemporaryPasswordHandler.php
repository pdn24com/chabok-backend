<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\IssueTemporaryPassword;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Application\UseCases\ProvisionPassword\ProvisionPasswordCommand;
use Modules\Iam\Application\UseCases\ProvisionPassword\ProvisionPasswordHandler;

final readonly class IssueTemporaryPasswordHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private ConnectionInterface $connection,
        private ProvisionPasswordHandler $provisionPasswordHandler,
        private ClockInterface $clock,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(IssueTemporaryPasswordCommand $command): void
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $password = $command->password;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        $this->connection->transaction(function () use ($actor, $userId, $password, $hqId, $correlationId): void {
            $user = $this->userRepository->lockByTenant($hqId, $userId);
            $this->userAccessGuard->assertTenantUser($user !== null, $user?->hq_id, $hqId);
            $this->provisionPasswordHandler->handle(new ProvisionPasswordCommand($userId, $password));
            $this->userRepository->update($userId, ['must_change_password' => true, 'updated_at' => $this->clock->now()]);
            $this->sessionLifecycle->revokeUserSessions($userId, 'TEMPORARY_PASSWORD_ISSUED');
            $this->auditWriter->write($hqId, $actor->userId, 'TEMPORARY_PASSWORD_ISSUED', 'USER', $userId, $correlationId, safeNote: 'Caller-supplied temporary password accepted; secret not retained in audit.');
        }, attempts: 3);
    }
}
