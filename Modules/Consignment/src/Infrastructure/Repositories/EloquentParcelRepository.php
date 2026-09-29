<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

final class EloquentParcelRepository implements ParcelRepositoryInterface
{
    /** Columns a measurement edit overwrites on an existing Parcel. */
    private const MEASUREMENT_COLUMNS = ['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm', 'updated_at'];

    public function idsByNumber(string $hqId, string $consignmentId): array
    {
        return ParcelRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->pluck('parcel_id', 'parcel_number')->map(strval(...))->all();
    }

    public function insert(array $rows): void
    {
        ParcelRecord::query()->insert($rows);
    }

    public function upsertMeasurements(array $rows): void
    {
        ParcelRecord::query()->upsert($rows, ['id'], self::MEASUREMENT_COLUMNS);
    }

    public function lockByIdsKeyedById(string $hqId, array $parcelIds): Collection
    {
        return ParcelRecord::query()->where('hq_id', $hqId)->whereIn('parcel_id', $parcelIds)
            ->orderBy('parcel_id')->lockForUpdate()->get()->keyBy('parcel_id');
    }

    public function activeRoutePlanId(string $hqId, string $consignmentId): ?string
    {
        $planId = ParcelRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->whereNotNull('active_route_plan_id')->value('active_route_plan_id');

        return $planId === null ? null : (string) $planId;
    }

    public function byIds(string $hqId, array $parcelIds): Collection
    {
        return ParcelRecord::query()->where('hq_id', $hqId)->whereIn('parcel_id', $parcelIds)->get();
    }

    public function statusCounts(string $hqId, array $consignmentIds): Collection
    {
        return ParcelRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->select('consignment_id', 'current_status')->selectRaw('COUNT(*) AS aggregate_count')
            ->groupBy('consignment_id', 'current_status')->get();
    }
}
