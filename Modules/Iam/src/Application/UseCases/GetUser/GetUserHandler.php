<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\GetUser;

use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Dto\UserDetailDto;
use Modules\Iam\Application\Ports\OperationalProfileWriterInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserAssignmentReaderInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\InvitationRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Application\UseCases\ListSessions\ListSessionsCommand;
use Modules\Iam\Application\UseCases\ListSessions\ListSessionsHandler;

final readonly class GetUserHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private InvitationRepositoryInterface $invitationRepository,
        private UserAssignmentReaderInterface $userAssignmentReader,
        private OperationalProfileWriterInterface $operationalProfileWriter,
        private ListSessionsHandler $listSessionsHandler,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(GetUserCommand $command): UserDetailDto
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.view', $hqId);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.view');
        $user = $this->userRepository->find($userId);
        $this->userAccessGuard->assertTenantUser($user !== null, $user?->hq_id, $hqId);
        $invitation = $this->invitationRepository->latestStatusForUser($userId);

        return new UserDetailDto(user: $user,
            assignments: $this->userAssignmentReader->forUser($hqId, $userId),
            invitationStatus: $invitation,
            driverProfile: $this->operationalProfileWriter->forUser($actor, $userId),
            sessions: $this->listSessionsHandler->handle(new ListSessionsCommand($userId)));
    }
}
