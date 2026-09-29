<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Dto\DeliveryTaskFiltersDto;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskHistoryRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final class EloquentDeliveryTaskRepository implements DeliveryTaskRepositoryInterface
{
    /** Rows written per statement, so a large manifest never builds one oversized query. */
    private const BATCH_SIZE = 100;

    /** Task states that still hold a Consignment for delivery. */
    private const OPEN_STATUSES = ['ASSIGNED', 'IN_PROGRESS'];

    public function findAtNode(?string $hqId, string $nodeId, string $taskId): ?DeliveryTaskRecord
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'delivery_task_id' => $taskId])->first();
    }

    public function lockAtNode(?string $hqId, string $nodeId, string $taskId): ?DeliveryTaskRecord
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'delivery_task_id' => $taskId])->lockForUpdate()->first();
    }

    public function findDetailAtNode(?string $hqId, string $nodeId, string $taskId): ?DeliveryTaskRecord
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'delivery_task_id' => $taskId])
            ->whereHas('consignment')->whereHas('node')
            ->with(['consignment', 'node', 'assignedDriver', 'history', 'resolution',
                'parcels' => fn ($parcels) => $parcels->where('hq_id', $hqId),
                'routeLegs' => fn ($legs) => $legs->whereHas('originNode')->whereHas('destinationNode')->with(['originNode', 'destinationNode'])])
            ->first();
    }

    public function lockForConsignment(?string $hqId, string $consignmentId): ?DeliveryTaskRecord
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
    }

    public function lockForConsignmentAtNode(?string $hqId, string $consignmentId, string $nodeId): ?DeliveryTaskRecord
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId])->lockForUpdate()->first();
    }

    public function listAtNode(?string $hqId, string $nodeId, DeliveryTaskFiltersDto $filters): Collection
    {
        $query = DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->whereHas('consignment')->with('consignment');
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }
        if ($filters->search !== null && $filters->search !== '') {
            $term = '%'.addcslashes(trim($filters->search), '%_\\').'%';
            $query->whereHas('consignment', fn ($consignment) => $consignment->where(fn ($search) => $search
                ->where('consignment_number', 'like', $term)->orWhere('receiver_contact_name', 'like', $term)->orWhere('receiver_mobile', 'like', $term)));
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function driverHasOtherOpenTask(?string $hqId, string $driverId, ?string $currentTaskId): bool
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'assigned_driver_id' => $driverId])
            ->whereIn('status', self::OPEN_STATUSES)
            ->when($currentTaskId !== null, fn ($query) => $query->where('delivery_task_id', '!=', $currentTaskId))->exists();
    }

    public function driverOnManifestDelivery(?string $hqId, string $driverId, ?string $manifestId): bool
    {
        return DeliveryTaskRecord::query()
            ->where(['hq_id' => $hqId, 'assigned_driver_id' => $driverId, 'manifest_id' => $manifestId, 'status' => 'IN_PROGRESS'])->exists();
    }

    public function driverHasConflictingTask(?string $hqId, string $driverId, string $currentTaskId, ?string $manifestId): bool
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'assigned_driver_id' => $driverId])
            ->whereIn('status', self::OPEN_STATUSES)->where('delivery_task_id', '!=', $currentTaskId)
            ->where(fn ($query) => $query->whereNull('manifest_id')->orWhere('manifest_id', '!=', $manifestId))->exists();
    }

    public function updateExpectedVersion(string $taskId, int $expectedVersion, array $changes): void
    {
        DeliveryTaskRecord::query()->where(['delivery_task_id' => $taskId, 'version' => $expectedVersion])->update($changes);
    }

    public function update(string $taskId, array $changes): void
    {
        DeliveryTaskRecord::query()->where('delivery_task_id', $taskId)->update($changes);
    }

    public function lockByConsignment(?string $hqId, array $consignmentIds): Collection
    {
        return DeliveryTaskRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->orderBy('consignment_id')->lockForUpdate()->get();
    }

    public function upsert(array $rows, array $columns): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            DeliveryTaskRecord::query()->upsert($chunk, ['id'], $columns);
        }
    }

    public function nextHistorySequence(string $taskId): int
    {
        return (int) DeliveryTaskHistoryRecord::query()->where('delivery_task_id', $taskId)->max('event_sequence') + 1;
    }
}
