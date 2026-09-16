<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Operations\Application\Repositories\PickupTaskRepository;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final class EloquentPickupTaskRepository implements PickupTaskRepository
{
    public function forNode(?string $hqId, string $nodeId): array
    {
        return PickupTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->orderByDesc('created_at')->get()->all();
    }

    public function confirmedConsignment(?string $hqId, string $nodeId, string $consignmentId): ?object
    {
        return DB::table('consignments')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId, 'current_status' => 'CFM'])->first();
    }

    public function forConsignment(?string $hqId, string $consignmentId): ?object
    {
        return PickupTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    public function find(?string $hqId, string $nodeId, string $id): ?object
    {
        return PickupTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'pickup_task_id' => $id])->first();
    }

    public function lock(?string $hqId, string $nodeId, string $id): ?object
    {
        return PickupTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'pickup_task_id' => $id])->lockForUpdate()->first();
    }

    public function eligibleDriver(?string $hqId, string $nodeId, string $driverId, string $capability): bool
    {
        return DB::table('drivers as d')->where([
            'd.hq_id' => $hqId,
            'd.driver_id' => $driverId,
            'd.home_node_id' => $nodeId,
            'd.status' => 'ACTIVE',
            'd.availability_status' => 'AVAILABLE',
        ])->whereExists(fn($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', $capability))->exists();
    }

    public function driverUser(?string $hqId, ?string $driverId): ?string
    {
        return DB::table('drivers')->where(['hq_id' => $hqId, 'driver_id' => $driverId])->value('user_id');
    }

    public function consignment(?string $hqId, string $consignmentId): ?object
    {
        return DB::table('consignments')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    public function node(?string $hqId, string $nodeId): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId])->first();
    }

    public function driver(?string $hqId, string $driverId): ?object
    {
        return DB::table('drivers')->where(['hq_id' => $hqId, 'driver_id' => $driverId])->first();
    }

    public function insert(array $attributes): void
    {
        PickupTaskRecord::query()->insert($attributes);
    }

    public function update(string $id, array $attributes): void
    {
        PickupTaskRecord::query()->where('pickup_task_id', $id)->update($attributes);
    }
}
