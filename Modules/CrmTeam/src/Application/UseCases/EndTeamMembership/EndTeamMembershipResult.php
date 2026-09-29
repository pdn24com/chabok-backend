<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\EndTeamMembership;

use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

/** The membership as it stands once ended. */
final readonly class EndTeamMembershipResult
{
    public function __construct(
        public TeamMemberRecord $membership,
    ) {}
}
