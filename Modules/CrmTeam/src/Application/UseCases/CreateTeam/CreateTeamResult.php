<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\CreateTeam;

use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

final readonly class CreateTeamResult
{
    public function __construct(public TeamRecord $team, public TeamMemberRecord $supervisorMembership) {}
}
