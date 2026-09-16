<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\UpdateUser;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Application\Data\UserData;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class UpdateUserHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private TransactionManager $transactions,
        private UserRepository $users,
        private Clock $clock,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(UpdateUserCommand $command): UpdateUserResult
    {
        return new UpdateUserResult($this->execute($command->actor, $command->userId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, array $input, string $correlationId): array
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        return $this->transactions->run(function () use ($actor, $userId, $input, $hqId, $correlationId): array {
            $before = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->policy->assertTenantUser($before, $hqId);
            $changes = array_intersect_key($input, array_flip(['first_name', 'last_name', 'display_name']));
            $changes['updated_at'] = $this->clock->now();
            $this->users->update($userId, $changes);
            $after = $this->users->findById($userId);
            $this->audit->write($hqId, $actor->userId, 'USER_PROFILE_UPDATED', 'USER', $userId, $correlationId, UserData::publicData($before), UserData::publicData($after));
            return UserData::publicData($after);
        });
    }
}
