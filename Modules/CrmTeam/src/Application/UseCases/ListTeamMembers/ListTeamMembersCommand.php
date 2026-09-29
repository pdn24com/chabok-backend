<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeamMembers;

use Modules\CrmTeam\Application\Dto\TeamMemberFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListTeamMembersCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public TeamMemberFiltersDto $filters = new TeamMemberFiltersDto,
    ) {}
}
