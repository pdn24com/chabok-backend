<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Modules\Operations\Application\Contracts\ManifestTaskAccess;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final class EloquentManifestTaskAccess implements ManifestTaskAccess
{
    public function pickupAssignmentExists(?string $hqId, ?string $consignmentId, ?string $assignedDriverId, string $node): bool
    {
        return PickupTaskRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'node_id' => $node,
            'assigned_driver_id' => $assignedDriverId,
        ])->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'COMPLETED'])->exists();
    }

    public function completedPickupExists(?string $hqId, ?string $consignmentId, ?string $currentCustodianId, string $node): bool
    {
        return PickupTaskRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'node_id' => $node,
            'assigned_driver_id' => $currentCustodianId,
            'status' => 'COMPLETED',
        ])->exists();
    }

    public function deliveryInProgress(?string $hqId, ?string $consignmentId, ?string $assignedDriverId, string $node): bool
    {
        return DeliveryTaskRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'node_id' => $node,
            'assigned_driver_id' => $assignedDriverId,
            'status' => 'IN_PROGRESS',
        ])->exists();
    }

    public function driversByIds(array $referenceIds, string $hq): array
    {
        return DriverRecord::query()->toBase()->where('hq_id', $hq)->whereIn('driver_id', $referenceIds)->get()->all();
    }

    public function vehiclesByIds(array $referenceIds, string $hq): array
    {
        return VehicleRecord::query()->toBase()->where('hq_id', $hq)->whereIn('vehicle_id', $referenceIds)->get()->all();
    }

    public function pickupContexts(string $hq, string $node): array
    {
        return PickupTaskRecord::query()->toBase()->from('pickup_tasks as t')->join('consignments as c', 'c.consignment_id', '=', 't.consignment_id')->where(['t.hq_id' => $hq, 't.node_id' => $node])->whereIn('t.status', ['ASSIGNED', 'IN_PROGRESS', 'COMPLETED'])->orderBy('c.consignment_number')->get(['t.*', 'c.consignment_number'])->all();
    }

    public function deliveryContexts(string $hq, string $node): array
    {
        return DeliveryTaskRecord::query()->toBase()->from('delivery_tasks as t')->join('consignments as c', 'c.consignment_id', '=', 't.consignment_id')->where(['t.hq_id' => $hq, 't.node_id' => $node, 't.status' => 'IN_PROGRESS'])->orderBy('c.consignment_number')->get(['t.*', 'c.consignment_number'])->all();
    }

    public function driver(string $hq, ?string $id): ?object
    {
        return DriverRecord::query()->toBase()->where(['hq_id' => $hq, 'driver_id' => $id])->first();
    }

    public function driverHasCapability(string $hq, ?string $id, string $capability): bool
    {
        return DB::table('driver_capabilities')->where(['hq_id' => $hq, 'driver_id' => $id, 'capability' => $capability])->exists();
    }

    public function vehicle(string $hq, ?string $id): ?object
    {
        return VehicleRecord::query()->toBase()->where(['hq_id' => $hq, 'vehicle_id' => $id])->first();
    }

    public function availableDrivers(string $hq, array $accessibleNodeIds): array
    {
        return DriverRecord::query()->toBase()->from('drivers as d')->where(['d.hq_id' => $hq, 'd.status' => 'ACTIVE'])->whereIn('d.home_node_id', $accessibleNodeIds)->whereIn('d.availability_status', ['AVAILABLE', 'ON_MISSION'])->whereExists(fn(Builder $q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.hq_id', $hq)->whereIn('dc.capability', ['PICKUP', 'LINEHAUL', 'DELIVERY']))->orderBy('d.driver_code')->get(['d.*'])->all();
    }

    public function driverCapabilities(?string $driverId, string $hq): array
    {
        return DB::table('driver_capabilities')->where(['hq_id' => $hq, 'driver_id' => $driverId])->whereIn('capability', ['PICKUP', 'LINEHAUL', 'DELIVERY'])->orderBy('capability')->pluck('capability')->all();
    }

    public function availableVehicles(string $hq, string $node): array
    {
        return VehicleRecord::query()->toBase()->where(['hq_id' => $hq, 'home_node_id' => $node, 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE'])->orderBy('vehicle_code')->get()->all();
    }

    public function assignTenantDriverMission(?string $hqId, ?string $assignedDriverId, array $changes): void
    {
        DriverRecord::query()->toBase()->where(['hq_id' => $hqId, 'driver_id' => $assignedDriverId, 'availability_status' => 'AVAILABLE'])->update($changes);
    }

    public function assignTenantVehicleMission(?string $hqId, ?string $assignedVehicleId, array $changes): void
    {
        VehicleRecord::query()->toBase()->where(['hq_id' => $hqId, 'vehicle_id' => $assignedVehicleId, 'availability_status' => 'AVAILABLE'])->update($changes);
    }

    public function releaseTenantDriverMission(?string $hqId, ?string $assignedDriverId, array $changes): void
    {
        DriverRecord::query()->toBase()->where(['hq_id' => $hqId, 'driver_id' => $assignedDriverId, 'availability_status' => 'ON_MISSION'])->update($changes);
    }

    public function releaseTenantVehicleMission(?string $hqId, ?string $assignedVehicleId, array $changes): void
    {
        VehicleRecord::query()->toBase()->where(['hq_id' => $hqId, 'vehicle_id' => $assignedVehicleId, 'availability_status' => 'ON_MISSION'])->update($changes);
    }

    public function lockPickup(?string $hqId, ?string $consignmentId): ?object
    {
        return PickupTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
    }

    public function insertPickup(array $attributes): void
    {
        PickupTaskRecord::query()->toBase()->insert($attributes);
    }

    public function updatePickup(?string $pickupTaskId, array $changes): void
    {
        PickupTaskRecord::query()->toBase()->where('pickup_task_id', $pickupTaskId)->update($changes);
    }

    public function assignDriverMission(?string $assignedDriverId, array $changes): void
    {
        DriverRecord::query()->toBase()->where(['driver_id' => $assignedDriverId, 'availability_status' => 'AVAILABLE'])->update($changes);
    }

    public function completePickup(?string $consignmentId, array $changes): void
    {
        $changes['version'] = DB::raw('version + 1');
        PickupTaskRecord::query()->toBase()->where('consignment_id', $consignmentId)->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])->update($changes);
    }

    public function failPickup(?string $consignmentId, array $changes): void
    {
        $changes['version'] = DB::raw('version + 1');
        PickupTaskRecord::query()->toBase()->where('consignment_id', $consignmentId)->update($changes);
    }

    public function releaseDriverMission(?string $currentCustodianId, array $changes): void
    {
        DriverRecord::query()->toBase()->where(['driver_id' => $currentCustodianId, 'availability_status' => 'ON_MISSION'])->update($changes);
    }

    public function lockDelivery(?string $hqId, ?string $consignmentId): ?object
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
    }

    public function updateDelivery(?string $deliveryTaskId, array $changes): void
    {
        DeliveryTaskRecord::query()->toBase()->where('delivery_task_id', $deliveryTaskId)->update($changes);
    }
}
