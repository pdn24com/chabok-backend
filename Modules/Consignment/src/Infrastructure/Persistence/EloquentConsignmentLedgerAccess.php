<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Application\Dto\ParcelTransitionDto;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;

final class EloquentConsignmentLedgerAccess implements ConsignmentLedgerAccessInterface
{
    public function __construct(private ClockInterface $clock) {}

    public function lockConsignment(?string $hqId, string $consignmentId): ?ConsignmentRecord
    {
        return ConsignmentRecord::query()
            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->lockForUpdate()
            ->first();
    }

    public function lockParcels(?string $hqId, string $consignmentId): Collection
    {
        return ParcelRecord::query()
            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->lockForUpdate()
            ->get();
    }

    public function setDeliveryDriver(string $consignmentId, string $driverId): void
    {
        ConsignmentRecord::query()->where('consignment_id', $consignmentId)->update(['delivery_man_id' => $driverId]);
    }

    public function setDeliveryNode(
        string $consignmentId,
        string $nodeId,
        DateTimeImmutable $at,
    ): void {
        ConsignmentRecord::query()->where('consignment_id', $consignmentId)->update(['delivery_node_id' => $nodeId, 'updated_at' => $at]);
    }

    public function setPickupDriver(string $consignmentId, string $driverId): void
    {
        ConsignmentRecord::query()->where('consignment_id', $consignmentId)->update(['pickup_man_id' => $driverId]);
    }

    public function applyParcelTransition(string $hqId, string $consignmentId, Collection $parcels, ParcelTransitionDto $transition): void
    {
        if ($parcels->isEmpty()) {
            return;
        }
        // The consignment lock serializes both sequence allocation and immutable history writes.
        $statusSequence = $this->nextStatusSequence($consignmentId, $hqId);
        $custodySequence = $this->nextCustodySequence($consignmentId);
        $now = $this->clock->now();
        $statuses = [];
        $custodies = [];
        foreach ($parcels as $parcel) {
            $statuses[] = [

                'hq_id' => $hqId,
                'event_sequence' => $statusSequence++,
                'consignment_id' => $consignmentId,
                'parcel_id' => $parcel->parcel_id,
                'previous_status' => $transition->fromStatus,
                'new_status' => $transition->toStatus,
                'initiator_id' => $transition->actorId,
                'node_id' => $transition->nodeId,
                'driver_id' => $transition->driverId,
                'manifest_id' => $transition->manifestId,
                'correlation_id' => $transition->correlationId,
                'reason_code' => $transition->reasonCode,
                'note' => $transition->safeNote,
                'created_at' => $now,
            ];
            $custodies[] = [

                'hq_id' => $hqId,
                'event_sequence' => $custodySequence++,
                'consignment_id' => $consignmentId,
                'parcel_id' => $parcel->parcel_id,
                'from_node_id' => $parcel->current_node_id,
                'to_node_id' => $transition->nodeId,
                'from_custody_type' => $parcel->current_custody_type,
                'to_custody_type' => $transition->custodyType,
                'from_custodian_id' => $parcel->current_custodian_id,
                'to_custodian_id' => $transition->custodianId,
                'command_name' => $transition->command,
                'initiator_id' => $transition->actorId,
                'manifest_id' => $transition->manifestId,
                'route_plan_id' => $transition->routePlanId,
                'route_plan_leg_id' => $transition->routePlanLegId,
                'created_at' => $now,
            ];
        }
        ParcelRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->whereIn('parcel_id', $parcels->pluck('parcel_id')->all())
            ->increment('version', 1, [
                'current_status' => $transition->toStatus,
                'current_node_id' => $transition->nodeId,
                'current_custody_type' => $transition->custodyType,
                'current_custodian_id' => $transition->custodianId,
                'updated_at' => $now,
            ]);
        // Keep batches bounded for database parameter limits, without a query for each parcel.
        foreach (array_chunk($statuses, 100) as $batch) {
            StatusEventRecord::query()->insert($batch);
        }
        foreach (array_chunk($custodies, 100) as $batch) {
            CustodyEventRecord::query()->insert($batch);
        }
    }

    public function setActiveRoute(
        string $hqId,
        string $consignmentId,
        string $planId,
        string $legId,
    ): void {
        ParcelRecord::query()
            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->update(['active_route_plan_id' => $planId, 'active_route_plan_leg_id' => $legId]);
    }

    private function nextStatusSequence(string $consignmentId, ?string $hqId = null): int
    {
        return (int) StatusEventRecord::query()
            ->where('consignment_id', $consignmentId)
            ->when($hqId !== null, fn ($q) => $q->where('hq_id', $hqId))
            ->max('event_sequence') + 1;
    }

    private function nextCustodySequence(string $consignmentId): int
    {
        return (int) CustodyEventRecord::query()->where('consignment_id', $consignmentId)->max('event_sequence') + 1;
    }
}
