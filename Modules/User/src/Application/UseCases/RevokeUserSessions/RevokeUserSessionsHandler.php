<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\RevokeUserSessions;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class RevokeUserSessionsHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private TransactionManager $transactions,
        private UserRepository $users,
        private UserSessionManager $sessions,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(RevokeUserSessionsCommand $command): RevokeUserSessionsResult
    {
        return new RevokeUserSessionsResult($this->execute($command->actor, $command->userId, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, string $correlationId): int
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        return $this->transactions->run(function () use ($actor, $userId, $hqId, $correlationId): int {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->policy->assertTenantUser($user, $hqId);
            $count = $this->sessions->revokeUserSessions($userId, 'ADMIN_REVOKED');
            $this->audit->write($hqId, $actor->userId, 'USER_SESSIONS_REVOKED', 'USER', $userId, $correlationId, safeNote: "{$count} sessions revoked.");
            return $count;
        });
    }
}
