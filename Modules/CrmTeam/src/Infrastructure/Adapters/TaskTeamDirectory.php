<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Adapters;

use Modules\CrmTask\Application\Ports\TeamDirectoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;

/**
 * The team behind a task referral. CrmTask records the team only as history, so it asks here rather
 * than reading the team tables itself.
 */
final readonly class TaskTeamDirectory implements TeamDirectoryInterface
{
    public function __construct(private TeamRepositoryInterface $teamRepository) {}

    public function activeExistsForTenant(string $hqId, string $teamId): bool
    {
        return $this->teamRepository->activeExistsForTenant($hqId, $teamId);
    }
}
