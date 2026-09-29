<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverCapabilityRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final class EloquentManifestTaskAccess implements ManifestTaskAccessInterface
{
    public function driversByIds(array $referenceIds, string $hq): Collection
    {
        return DriverRecord::query()
            ->where('hq_id', $hq)
            ->whereIn('driver_id', $referenceIds)
            ->get();
    }

    public function vehiclesByIds(array $referenceIds, string $hq): Collection
    {
        return VehicleRecord::query()
            ->where('hq_id', $hq)
            ->whereIn('vehicle_id', $referenceIds)
            ->get();
    }

    public function pickupContexts(string $hq, string $node): Collection
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hq, 'node_id' => $node])->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'COMPLETED'])
            ->whereHas('consignment')->with('consignment')->get()->sortBy('consignment.consignment_number')->values();
    }

    public function deliveryContexts(string $hq, string $node): Collection
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hq, 'node_id' => $node])->where('status', 'IN_PROGRESS')
            ->whereHas('consignment')->with('consignment')->get()->sortBy('consignment.consignment_number')->values();
    }

    public function pickupsByConsignment(?string $hqId, string $nodeId, array $consignmentIds): Collection
    {
        return PickupTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])
            ->whereIn('consignment_id', $consignmentIds)->get()->keyBy('consignment_id');
    }

    public function deliveriesByConsignment(?string $hqId, string $nodeId, array $consignmentIds): Collection
    {
        return DeliveryTaskRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])
            ->whereIn('consignment_id', $consignmentIds)->get()->keyBy('consignment_id');
    }

    public function driver(string $hq, ?string $id): ?DriverRecord
    {
        return DriverRecord::query()
            ->where(['hq_id' => $hq, 'driver_id' => $id])
            ->first();
    }

    public function driverHasCapability(
        string $hq,
        ?string $id,
        DriverCapability $capability,
    ): bool {
        return DriverCapabilityRecord::query()->where([
            'hq_id' => $hq,
            'driver_id' => $id,
            'capability' => $capability->value,
        ])->exists();
    }

    public function vehicle(string $hq, ?string $id): ?VehicleRecord
    {
        return VehicleRecord::query()
            ->where(['hq_id' => $hq, 'vehicle_id' => $id])
            ->first();
    }

    public function availableDrivers(string $hq, array $accessibleNodeIds): Collection
    {
        return DriverRecord::query()->where(['hq_id' => $hq, 'status' => 'ACTIVE'])->whereIn('home_node_id', $accessibleNodeIds)
            ->whereIn('availability_status', ['AVAILABLE', 'ON_MISSION'])
            ->whereHas('capabilities', fn ($capabilities) => $capabilities->where('hq_id', $hq)->whereIn('capability', ['PICKUP', 'LINEHAUL', 'DELIVERY']))
            ->with(['capabilities' => fn ($capabilities) => $capabilities->where('hq_id', $hq)->whereIn('capability', ['PICKUP', 'LINEHAUL', 'DELIVERY'])->orderBy('capability')])
            ->orderBy('driver_code')->get();
    }

    public function availableVehicles(string $hq, string $node): Collection
    {
        return VehicleRecord::query()
            ->where([
                'hq_id' => $hq,
                'home_node_id' => $node,
                'status' => 'ACTIVE',
                'availability_status' => 'AVAILABLE',
            ])
            ->orderBy('vehicle_code')
            ->get();
    }

    public function assignTenantDriverMission(
        ?string $hqId,
        ?string $assignedDriverId,
        array $changes,
    ): void {
        DriverRecord::query()
            ->where([
                'hq_id' => $hqId,
                'driver_id' => $assignedDriverId,
                'availability_status' => 'AVAILABLE',
            ])
            ->update($changes);
    }

    public function assignTenantVehicleMission(
        ?string $hqId,
        ?string $assignedVehicleId,
        array $changes,
    ): void {
        VehicleRecord::query()
            ->where([
                'hq_id' => $hqId,
                'vehicle_id' => $assignedVehicleId,
                'availability_status' => 'AVAILABLE',
            ])
            ->update($changes);
    }

    public function releaseTenantDriverMission(
        ?string $hqId,
        ?string $assignedDriverId,
        array $changes,
    ): void {
        DriverRecord::query()
            ->where([
                'hq_id' => $hqId,
                'driver_id' => $assignedDriverId,
                'availability_status' => 'ON_MISSION',
            ])
            ->update($changes);
    }

    public function releaseTenantVehicleMission(
        ?string $hqId,
        ?string $assignedVehicleId,
        array $changes,
    ): void {
        VehicleRecord::query()
            ->where([
                'hq_id' => $hqId,
                'vehicle_id' => $assignedVehicleId,
                'availability_status' => 'ON_MISSION',
            ])
            ->update($changes);
    }

    public function lockPickup(?string $hqId, ?string $consignmentId): ?PickupTaskRecord
    {
        return PickupTaskRecord::query()
            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->lockForUpdate()
            ->first();
    }

    public function insertPickup(array $attributes): void
    {
        (new PickupTaskRecord)->forceFill($attributes)->save();
    }

    public function updatePickup(?string $pickupTaskId, array $changes): void
    {
        PickupTaskRecord::query()
            ->where('pickup_task_id', $pickupTaskId)
            ->update($changes);
    }

    public function assignDriverMission(?string $assignedDriverId, array $changes): void
    {
        DriverRecord::query()
            ->where(['driver_id' => $assignedDriverId, 'availability_status' => 'AVAILABLE'])
            ->update($changes);
    }

    public function completePickup(?string $consignmentId, array $changes): void
    {
        PickupTaskRecord::query()
            ->where('consignment_id', $consignmentId)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->increment('version', 1, $changes);
    }

    public function failPickup(?string $consignmentId, array $changes): void
    {
        PickupTaskRecord::query()
            ->where('consignment_id', $consignmentId)
            ->increment('version', 1, $changes);
    }

    public function releaseDriverMission(?string $currentCustodianId, array $changes): void
    {
        DriverRecord::query()
            ->where(['driver_id' => $currentCustodianId, 'availability_status' => 'ON_MISSION'])
            ->update($changes);
    }

    public function lockDelivery(?string $hqId, ?string $consignmentId): ?DeliveryTaskRecord
    {
        return DeliveryTaskRecord::query()
            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->lockForUpdate()
            ->first();
    }

    public function updateDelivery(?string $deliveryTaskId, array $changes): void
    {
        DeliveryTaskRecord::query()
            ->where('delivery_task_id', $deliveryTaskId)
            ->update($changes);
    }
}
