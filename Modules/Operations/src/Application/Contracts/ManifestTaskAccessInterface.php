<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */
use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

interface ManifestTaskAccessInterface
{
    public function driversByIds(array $referenceIds, string $hq): Collection;

    public function vehiclesByIds(array $referenceIds, string $hq): Collection;

    public function pickupContexts(string $hq, string $node): Collection;

    public function deliveryContexts(string $hq, string $node): Collection;

    public function driver(string $hq, ?string $id): ?DriverRecord;

    public function driverHasCapability(
        string $hq,
        ?string $id,
        DriverCapability $capability,
    ): bool;

    public function vehicle(string $hq, ?string $id): ?VehicleRecord;

    public function availableDrivers(string $hq, array $accessibleNodeIds): Collection;

    public function availableVehicles(string $hq, string $node): Collection;

    public function assignTenantDriverMission(
        ?string $hqId,
        ?string $assignedDriverId,
        array $changes,
    ): void;

    public function assignTenantVehicleMission(
        ?string $hqId,
        ?string $assignedVehicleId,
        array $changes,
    ): void;

    public function releaseTenantDriverMission(
        ?string $hqId,
        ?string $assignedDriverId,
        array $changes,
    ): void;

    public function releaseTenantVehicleMission(
        ?string $hqId,
        ?string $assignedVehicleId,
        array $changes,
    ): void;

    public function lockPickup(?string $hqId, ?string $consignmentId): ?PickupTaskRecord;

    public function insertPickup(array $attributes): void;

    public function updatePickup(?string $pickupTaskId, array $changes): void;

    public function assignDriverMission(?string $assignedDriverId, array $changes): void;

    public function completePickup(?string $consignmentId, array $changes): void;

    public function failPickup(?string $consignmentId, array $changes): void;

    public function releaseDriverMission(?string $currentCustodianId, array $changes): void;

    /** Pickup tasks at a Node for the given Consignments, keyed by Consignment. @param list<string> $consignmentIds @return Collection<string, PickupTaskRecord> */
    public function pickupsByConsignment(?string $hqId, string $nodeId, array $consignmentIds): Collection;

    /** Delivery tasks at a Node for the given Consignments, keyed by Consignment. @param list<string> $consignmentIds @return Collection<string, DeliveryTaskRecord> */
    public function deliveriesByConsignment(?string $hqId, string $nodeId, array $consignmentIds): Collection;

    public function lockDelivery(?string $hqId, ?string $consignmentId): ?DeliveryTaskRecord;

    public function updateDelivery(?string $deliveryTaskId, array $changes): void;
}
