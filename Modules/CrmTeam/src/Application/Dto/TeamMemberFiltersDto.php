<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Dto;

use Modules\CrmTeam\Domain\Enums\MembershipStatus;

final readonly class TeamMemberFiltersDto
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
        public ?string $teamId = null,
        public ?MembershipStatus $status = null,
        /** Matches the display name or the username of the person. */
        public ?string $search = null,
    ) {}
}
