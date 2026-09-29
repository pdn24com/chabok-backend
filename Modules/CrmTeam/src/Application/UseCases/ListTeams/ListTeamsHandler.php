<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeams;

use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;

final readonly class ListTeamsHandler
{
    public function __construct(
        private TeamAccessGuardInterface $accessGuard,
        private TeamRepositoryInterface $teamRepository,
    ) {}

    public function handle(ListTeamsCommand $command): ListTeamsResult
    {
        // A tenant keeps a handful of teams, so the whole set is returned and there is no page to ask for.
        return new ListTeamsResult($this->teamRepository->listForTenant($this->accessGuard->assertCanRead($command->actor), $command->status));
    }
}
