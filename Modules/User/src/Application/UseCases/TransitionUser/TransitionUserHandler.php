<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\TransitionUser;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\UserAdministrationPolicy;
use Modules\User\Domain\UserLifecyclePolicy;

final readonly class TransitionUserHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private TransactionManager $transactions,
        private UserRepository $users,
        private UserLifecyclePolicy $lifecycle,
        private Clock $clock,
        private UserSessionManager $sessions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    )
    {
    }

    public function handle(TransitionUserCommand $command): TransitionUserResult
    {
        return new TransitionUserResult($this->execute($command->actor, $command->userId, $command->to, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, string $to, string $correlationId): array
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        return $this->transactions->run(function () use ($actor, $userId, $to, $hqId, $correlationId): array {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->policy->assertTenantUser($user, $hqId);
            $this->lifecycle->assertTransition((string) $user['status'], $to);
            $this->users->update($userId, [
                'status' => $to,
                'activated_at' => $to === 'ACTIVE' ? $this->clock->now() : $user['activated_at'],
                'updated_at' => $this->clock->now(),
            ]);
            if (in_array($to, ['SUSPENDED', 'DEACTIVATED'], true)) {
                $this->sessions->revokeUserSessions($userId, "USER_{$to}");
            }
            $this->audit->write($hqId, $actor->userId, "USER_{$to}", 'USER', $userId, $correlationId, ['status' => $user['status']], ['status' => $to]);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.status_changed', $correlationId, ['user_id' => $userId, 'from' => $user['status'], 'to' => $to]);
            return ['user_id' => $userId, 'status' => $to];
        });
    }
}
