<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeams;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

final readonly class ListTeamsHandler
{
    public function __construct(
        private TeamAccessGuardInterface $accessGuard,
        private TeamRepositoryInterface $teamRepository,
    ) {}

    /** @return Collection<int, TeamRecord> */
    public function handle(ListTeamsCommand $command): Collection
    {
        // A tenant keeps a handful of teams, so the whole set is returned and there is no page to ask for.
        return $this->teamRepository->listForTenant($this->accessGuard->assertCanRead($command->actor), $command->status);
    }
}
