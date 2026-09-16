<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\ListUsers;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class ListUsersHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private UserRepository $users,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
    )
    {
    }

    public function handle(ListUsersCommand $command): ListUsersResult
    {
        return new ListUsersResult($this->execute($command->actor, $command->page, $command->pageSize, $command->search, $command->status, $command->nodeId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?string $nodeId = null,
    ): Page
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.view', $hqId);
        return $this->users->paginate($hqId, $page, $pageSize, $search, $status, $this->scopeAuthorizer->visibleUserIds($actor, $nodeId));
    }
}
