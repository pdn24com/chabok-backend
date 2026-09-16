<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\IssueTemporaryPassword;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class IssueTemporaryPasswordHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private TransactionManager $transactions,
        private UserRepository $users,
        private IdentityProvisioner $identity,
        private Clock $clock,
        private UserSessionManager $sessions,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(IssueTemporaryPasswordCommand $command): IssueTemporaryPasswordResult
    {
        $this->execute($command->actor, $command->userId, $command->password, $command->correlationId);
        return new IssueTemporaryPasswordResult();
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, string $password, string $correlationId): void
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        $this->transactions->run(function () use ($actor, $userId, $password, $hqId, $correlationId): void {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->policy->assertTenantUser($user, $hqId);
            $this->identity->provisionPassword($userId, $password);
            $this->users->update($userId, ['must_change_password' => true, 'updated_at' => $this->clock->now()]);
            $this->sessions->revokeUserSessions($userId, 'TEMPORARY_PASSWORD_ISSUED');
            $this->audit->write($hqId, $actor->userId, 'TEMPORARY_PASSWORD_ISSUED', 'USER', $userId, $correlationId, safeNote: 'Caller-supplied temporary password accepted; secret not retained in audit.');
        });
    }
}
