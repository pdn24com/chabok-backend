<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\InviteUser;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class InviteUserHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private TransactionManager $transactions,
        private UserRepository $users,
        private IdentityProvisioner $identity,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(InviteUserCommand $command): InviteUserResult
    {
        $this->execute($command->actor, $command->userId, $command->channel, $command->correlationId);
        return new InviteUserResult();
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, string $channel, string $correlationId): void
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        $this->transactions->run(function () use ($actor, $userId, $channel, $hqId, $correlationId): void {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->policy->assertTenantUser($user, $hqId);
            $this->identity->createInvitation($user, $channel, $actor->userId, $correlationId);
            $this->audit->write($hqId, $actor->userId, 'USER_INVITED', 'USER', $userId, $correlationId);
        });
    }
}
