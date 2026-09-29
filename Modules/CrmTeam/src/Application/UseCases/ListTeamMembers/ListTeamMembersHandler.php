<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeamMembers;

use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;

final readonly class ListTeamMembersHandler
{
    public function __construct(
        private TeamAccessGuardInterface $accessGuard,
        private TeamMemberRepositoryInterface $teamMemberRepository,
    ) {}

    public function handle(ListTeamMembersCommand $command): ListTeamMembersResult
    {
        return new ListTeamMembersResult($this->teamMemberRepository->paginateForTenant(
            $this->accessGuard->assertCanRead($command->actor), $command->filters,
        ));
    }
}
