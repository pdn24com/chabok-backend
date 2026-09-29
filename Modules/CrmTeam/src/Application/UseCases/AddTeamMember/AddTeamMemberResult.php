<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\AddTeamMember;

use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

/** The membership the user was enrolled with. */
final readonly class AddTeamMemberResult
{
    public function __construct(
        public TeamMemberRecord $membership,
    ) {}
}
