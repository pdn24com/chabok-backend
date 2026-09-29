<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeamMembers;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

final readonly class ListTeamMembersHandler
{
    public function __construct(
        private TeamAccessGuardInterface $accessGuard,
        private TeamMemberRepositoryInterface $teamMemberRepository,
    ) {}

    /** @return LengthAwarePaginator<TeamMemberRecord> */
    public function handle(ListTeamMembersCommand $command): LengthAwarePaginator
    {
        return $this->teamMemberRepository->paginateForTenant(
            $this->accessGuard->assertCanRead($command->actor), $command->filters);
    }
}
