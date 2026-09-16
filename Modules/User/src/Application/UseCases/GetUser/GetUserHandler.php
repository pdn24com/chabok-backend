<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\GetUser;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserAssignmentReader;
use Modules\User\Application\Contracts\UserInvitationReader;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Application\Data\UserData;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class GetUserHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private UserRepository $users,
        private UserInvitationReader $invitations,
        private UserAssignmentReader $assignmentReader,
        private \Modules\User\Application\Contracts\OperationalProfileWriter $operationalProfiles,
        private UserSessionManager $sessions,
    )
    {
    }

    public function handle(GetUserCommand $command): GetUserResult
    {
        return new GetUserResult($this->execute($command->actor, $command->userId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId): array
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.view', $hqId);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.view');
        $user = $this->users->findById($userId);
        $this->policy->assertTenantUser($user, $hqId);
        $invitation = $this->invitations->latestStatus($userId);
        return [
            'user' => UserData::publicData($user),
            'assignments' => $this->assignmentReader->forUser($hqId, $userId),
            'invitation_status' => $invitation,
            'driver_profile' => $this->operationalProfiles->forUser($actor, $userId),
            'sessions' => $this->sessions->listSessions($userId),
        ];
    }
}
