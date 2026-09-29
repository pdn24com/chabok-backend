<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeamMembers;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

/** One page of team memberships. */
final readonly class ListTeamMembersResult
{
    /**
     * @param  LengthAwarePaginator<TeamMemberRecord>  $members
     */
    public function __construct(
        public LengthAwarePaginator $members,
    ) {}
}
