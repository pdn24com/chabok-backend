<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Repositories;

use Modules\CrmTeam\Infrastructure\Persistence\Models\MembershipEventRecord;

interface MembershipEventRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): MembershipEventRecord;

    /**
     * Stamps every row of one bulk run with the same correlation, so the trail reads as a single action.
     *
     * @param  list<string>  $eventIds
     */
    public function correlate(string $hqId, array $eventIds, string $operationId): void;
}
