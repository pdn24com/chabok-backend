<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\AttachOperationalProfile;

use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\InitialAssignmentWriter;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class AttachOperationalProfileHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private TransactionManager $transactions,
        private UserRepository $users,
        private \Modules\User\Application\Contracts\OperationalProfileWriter $operationalProfiles,
        private InitialAssignmentWriter $assignments,
    )
    {
    }

    public function handle(AttachOperationalProfileCommand $command): AttachOperationalProfileResult
    {
        return new AttachOperationalProfileResult($this->execute($command->actor, $command->userId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, array $input, string $correlationId): array
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
        return $this->transactions->run(function () use ($actor, $userId, $input, $correlationId, $hqId): array {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->policy->assertTenantUser($user, $hqId);
            $assignment = $this->operationalProfiles->attach($actor, $userId, $input, $correlationId);
            if ($assignment !== null) {
                $this->assignments->assign($hqId, $userId, $actor->userId, [$assignment], $correlationId);
            }
            return ['driver_profile' => $this->operationalProfiles->forUser($actor, $userId)];
        });
    }
}
