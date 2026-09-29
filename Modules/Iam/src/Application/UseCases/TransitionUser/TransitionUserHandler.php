<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\TransitionUser;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Domain\Policies\UserLifecyclePolicy;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class TransitionUserHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private ConnectionInterface $connection,
        private UserLifecyclePolicy $userLifecyclePolicy,
        private ClockInterface $clock,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(TransitionUserCommand $command): UserRecord
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $to = $command->to;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');

        return $this->connection->transaction(function () use ($actor, $userId, $to, $hqId, $correlationId): UserRecord {
            $user = $this->userRepository->lockByTenant($hqId, $userId);
            $this->userAccessGuard->assertTenantUser($user !== null, $user?->hq_id, $hqId);
            $this->userLifecyclePolicy->assertTransition(UserStatus::from($user->status), $to);
            $previousStatus = $user->status;
            $this->userRepository->apply($user, [
                'status' => $to->value,
                'activated_at' => $to === UserStatus::Active ? $this->clock->now() : $user->activated_at,
                'updated_at' => $this->clock->now(),
            ]);
            if (in_array($to, [UserStatus::Suspended, UserStatus::Deactivated], true)) {
                $this->sessionLifecycle->revokeUserSessions($userId, "USER_{$to->value}");
            }
            $this->auditWriter->write($hqId, $actor->userId, "USER_{$to->value}", 'USER', $userId, $correlationId, ['status' => $previousStatus], ['status' => $to->value]);
            $this->outboxWriter->write($hqId, 'USER', $userId, 'iam.user.status_changed', $correlationId, [
                'user_id' => $userId,
                'from' => $previousStatus,
                'to' => $to->value,
            ]);

            return $user;
        }, attempts: 3);
    }
}
