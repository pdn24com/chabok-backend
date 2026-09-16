<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */

interface ManifestTaskAccess
{
    public function pickupAssignmentExists(?string $hqId, ?string $consignmentId, ?string $assignedDriverId, string $node): bool;

    public function completedPickupExists(?string $hqId, ?string $consignmentId, ?string $currentCustodianId, string $node): bool;

    public function deliveryInProgress(?string $hqId, ?string $consignmentId, ?string $assignedDriverId, string $node): bool;

    public function driversByIds(array $referenceIds, string $hq): array;

    public function vehiclesByIds(array $referenceIds, string $hq): array;

    public function pickupContexts(string $hq, string $node): array;

    public function deliveryContexts(string $hq, string $node): array;

    public function driver(string $hq, ?string $id): ?object;

    public function driverHasCapability(string $hq, ?string $id, string $capability): bool;

    public function vehicle(string $hq, ?string $id): ?object;

    public function availableDrivers(string $hq, array $accessibleNodeIds): array;

    public function driverCapabilities(?string $driverId, string $hq): array;

    public function availableVehicles(string $hq, string $node): array;

    public function assignTenantDriverMission(?string $hqId, ?string $assignedDriverId, array $changes): void;

    public function assignTenantVehicleMission(?string $hqId, ?string $assignedVehicleId, array $changes): void;

    public function releaseTenantDriverMission(?string $hqId, ?string $assignedDriverId, array $changes): void;

    public function releaseTenantVehicleMission(?string $hqId, ?string $assignedVehicleId, array $changes): void;

    public function lockPickup(?string $hqId, ?string $consignmentId): ?object;

    public function insertPickup(array $attributes): void;

    public function updatePickup(?string $pickupTaskId, array $changes): void;

    public function assignDriverMission(?string $assignedDriverId, array $changes): void;

    public function completePickup(?string $consignmentId, array $changes): void;

    public function failPickup(?string $consignmentId, array $changes): void;

    public function releaseDriverMission(?string $currentCustodianId, array $changes): void;

    public function lockDelivery(?string $hqId, ?string $consignmentId): ?object;

    public function updateDelivery(?string $deliveryTaskId, array $changes): void;
}
