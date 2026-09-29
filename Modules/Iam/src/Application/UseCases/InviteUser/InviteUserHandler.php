<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\InviteUser;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Application\UseCases\CreateInvitation\CreateInvitationCommand;
use Modules\Iam\Application\UseCases\CreateInvitation\CreateInvitationHandler;

final readonly class InviteUserHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private ConnectionInterface $connection,
        private CreateInvitationHandler $createInvitationHandler,
        private AuditWriterInterface $auditWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(InviteUserCommand $command): void
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $channel = $command->channel;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        $this->connection->transaction(function () use ($actor, $userId, $channel, $hqId, $correlationId): void {
            $user = $this->userRepository->lockByTenant($hqId, $userId);
            $this->userAccessGuard->assertTenantUser($user !== null, $user?->hq_id, $hqId);
            $this->createInvitationHandler->handle(new CreateInvitationCommand($user, $channel, $actor->userId, $correlationId));
            $this->auditWriter->write($hqId, $actor->userId, 'USER_INVITED', 'USER', $userId, $correlationId);
        }, attempts: 3);
    }
}
