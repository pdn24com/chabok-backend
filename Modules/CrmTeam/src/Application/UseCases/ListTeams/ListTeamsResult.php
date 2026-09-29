<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeams;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

/** Every team of the tenant; a tenant keeps a handful, so there is no page. */
final readonly class ListTeamsResult
{
    /**
     * @param  Collection<int, TeamRecord>  $teams
     */
    public function __construct(
        public Collection $teams,
    ) {}
}
