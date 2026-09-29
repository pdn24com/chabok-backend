<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Repositories;

use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Infrastructure\Persistence\Models\MembershipEventRecord;

final class EloquentMembershipEventRepository implements MembershipEventRepositoryInterface
{
    public function create(array $attributes): MembershipEventRecord
    {
        return MembershipEventRecord::query()->forceCreate($attributes);
    }

    public function correlate(string $hqId, array $eventIds, string $operationId): void
    {
        if ($eventIds === []) {
            return;
        }
        MembershipEventRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('membership_event_id', $eventIds)
            ->update(['operation_id' => $operationId]);
    }
}
