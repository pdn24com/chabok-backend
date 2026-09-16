<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;

final class EloquentConsignmentLedgerRepository implements ConsignmentLedgerRepository
{
    public function lockConsignment(?string $hqId, string $consignmentId): ?object
    {
        return ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
    }

    public function lockParcels(?string $hqId, string $consignmentId): array
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->get()->all();
    }

    public function parcelStatusCounts(?string $hqId, string $consignmentId): array
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->selectRaw('current_status, COUNT(*) AS aggregate_count')->groupBy('current_status')->pluck('aggregate_count', 'current_status')->map(static fn($count): int => (int) $count)->all();
    }

    public function updateConsignment(?string $hqId, string $consignmentId, array $attributes): void
    {
        ConsignmentRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->update($attributes);
    }

    public function setDeliveryDriver(string $consignmentId, string $driverId): void
    {
        ConsignmentRecord::query()->where('consignment_id', $consignmentId)->update(['delivery_man_id' => $driverId]);
    }

    public function setDeliveryNode(string $consignmentId, string $nodeId, \DateTimeImmutable $at): void
    {
        ConsignmentRecord::query()->where('consignment_id', $consignmentId)->update(['delivery_node_id' => $nodeId, 'updated_at' => $at]);
    }

    public function setPickupDriver(string $consignmentId, string $driverId): void
    {
        ConsignmentRecord::query()->where('consignment_id', $consignmentId)->update(['pickup_man_id' => $driverId]);
    }

    public function updateParcel(string $parcelId, array $attributes): void
    {
        ParcelRecord::query()->where('parcel_id', $parcelId)->update($attributes);
    }

    public function appendStatusEvent(array $attributes): void
    {
        StatusEventRecord::query()->insert($attributes);
    }

    public function appendCustodyEvent(array $attributes): void
    {
        CustodyEventRecord::query()->insert($attributes);
    }

    public function nextStatusSequence(string $consignmentId, ?string $hqId = null): int
    {
        return (int) StatusEventRecord::query()->where('consignment_id', $consignmentId)->when($hqId !== null, fn($q) => $q->where('hq_id', $hqId))->max('event_sequence') + 1;
    }

    public function nextCustodySequence(string $consignmentId): int
    {
        return (int) CustodyEventRecord::query()->where('consignment_id', $consignmentId)->max('event_sequence') + 1;
    }

    public function lockAtPickupNode(string $hqId, string $consignmentId, string $nodeId): ?object
    {
        return ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId])->lockForUpdate()->first();
    }

    public function setActiveRoute(string $hqId, string $consignmentId, string $planId, string $legId): void
    {
        ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->update(['active_route_plan_id' => $planId, 'active_route_plan_leg_id' => $legId]);
    }
}
