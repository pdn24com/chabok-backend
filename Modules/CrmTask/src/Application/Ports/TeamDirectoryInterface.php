<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Ports;

/**
 * The team a referral is recorded against. A team never owns a task — it only appears in the assignment
 * history as the context the work moved through.
 *
 * Implemented by Modules\CrmTeam.
 */
interface TeamDirectoryInterface
{
    public function activeExistsForTenant(string $hqId, string $teamId): bool;
}
