<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ListUsers;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Dto\UserSearchDto;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class ListUsersHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(ListUsersCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $page = $command->page;
        $pageSize = $command->pageSize;
        $search = $command->search;
        $status = $command->status;
        $nodeId = $command->nodeId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.view', $hqId);

        $visibleUserIds = $this->userScopeAuthorizer->visibleUserIds($actor, $nodeId);

        return $this->userRepository->search($hqId, $visibleUserIds, new UserSearchDto($status, $search, $page, $pageSize));
    }
}
