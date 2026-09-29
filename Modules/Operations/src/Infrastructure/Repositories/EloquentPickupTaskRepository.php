<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final class EloquentPickupTaskRepository implements PickupTaskRepositoryInterface
{
    /** Rows written per statement, so a large manifest never builds one oversized query. */
    private const BATCH_SIZE = 100;

    /** Task states that still hold a Consignment for pickup. */
    private const OPEN_STATUSES = ['ASSIGNED', 'IN_PROGRESS'];

    public function findAtNode(?string $hqId, string $nodeId, string $taskId): ?PickupTaskRecord
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'pickup_task_id' => $taskId])->first();
    }

    public function lockAtNode(?string $hqId, string $nodeId, string $taskId): ?PickupTaskRecord
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'pickup_task_id' => $taskId])->lockForUpdate()->first();
    }

    public function findForConsignment(?string $hqId, string $consignmentId): ?PickupTaskRecord
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    public function listAtNode(?string $hqId, string $nodeId): Collection
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])
            ->with($this->detailRelations($hqId))->orderByDesc('created_at')->get();
    }

    public function findDetailAtNode(?string $hqId, string $nodeId, string $taskId): ?PickupTaskRecord
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])
            ->with($this->detailRelations($hqId))->where('pickup_task_id', $taskId)->first();
    }

    public function create(array $attributes): void
    {
        PickupTaskRecord::query()->forceCreate($attributes);
    }

    public function lockByConsignmentKeyed(?string $hqId, array $consignmentIds): Collection
    {
        return $this->lockByConsignment($hqId, $consignmentIds)->keyBy('consignment_id');
    }

    public function lockByConsignment(?string $hqId, array $consignmentIds): Collection
    {
        return PickupTaskRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->orderBy('consignment_id')->lockForUpdate()->get();
    }

    public function completeOpenTasks(?string $hqId, array $consignmentIds, array $changes): void
    {
        PickupTaskRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->whereIn('status', self::OPEN_STATUSES)->increment('version', 1, $changes);
    }

    public function insert(array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            PickupTaskRecord::query()->insert($chunk);
        }
    }

    public function upsert(array $rows, array $columns): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            PickupTaskRecord::query()->upsert($chunk, ['id'], $columns);
        }
    }

    /** Every related row a task response renders stays inside the acting tenant. @return array<string, callable> */
    private function detailRelations(?string $hqId): array
    {
        return [
            'consignment' => fn ($related) => $related->where('hq_id', $hqId),
            'node' => fn ($related) => $related->where('hq_id', $hqId),
            'assignedDriver' => fn ($related) => $related->where('hq_id', $hqId),
        ];
    }
}
