<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityRecord;

final class EloquentActivityRepository implements ActivityRepositoryInterface
{
    public function existsForCustomer(string $hqId, string $customerId, string $activityId): bool
    {
        return ActivityRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'activity_id' => $activityId])
            ->exists();
    }

    public function historyForCustomer(string $hqId, string $customerId, array $types): Collection
    {
        return ActivityRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            ->whereIn('type', $types)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get(['id', 'type', 'body', 'result', 'occurred_at', 'direction', 'channel', 'contact_value']);
    }

    public function create(array $attributes): ActivityRecord
    {
        return ActivityRecord::query()->forceCreate($attributes);
    }
}
