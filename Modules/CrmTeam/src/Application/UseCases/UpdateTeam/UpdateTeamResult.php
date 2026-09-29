<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\UpdateTeam;

use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

/** The team as it stands after the change. */
final readonly class UpdateTeamResult
{
    public function __construct(
        public TeamRecord $team,
    ) {}
}
