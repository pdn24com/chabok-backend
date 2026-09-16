<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface FleetRepository
{
    public function paginateDrivers(?string $hqId, array $visibleNodes, array $filters): Page;

    public function paginateVehicles(?string $hqId, array $visibleNodes, array $filters): Page;

    public function capabilitiesForDrivers(array $ids): array;

    public function findDriver(?string $hqId, string $driverId): ?object;

    public function findDriverForUpdate(?string $hqId, string $driverId): ?object;

    public function insertDriver(array $attributes): void;

    public function updateDriver(?string $hqId, string $driverId, int $expected, array $attributes): void;

    public function findVehicle(?string $hqId, string $vehicleId): ?object;

    public function findVehicleForUpdate(?string $hqId, string $vehicleId): ?object;

    public function insertVehicle(array $attributes): void;

    public function updateVehicle(?string $hqId, string $vehicleId, int $expected, array $attributes): void;

    public function driverCapabilities(string $driverId): array;

    public function activeNodeExists(?string $hqId, string $nodeId): bool;

    public function tenantUserExists(?string $hqId, mixed $userId): bool;

    public function userHasDriver(mixed $userId, ?string $currentDriverId): bool;

    public function removeCapabilities(string $driverId): void;

    public function insertCapability(array $attributes): void;
}
