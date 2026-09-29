<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Dto\FleetFiltersDto;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

interface DriverRepositoryInterface
{
    public function findByTenant(?string $hqId, ?string $driverId): ?DriverRecord;

    public function findWithCapabilities(?string $hqId, string $driverId): ?DriverRecord;

    public function lockByTenant(?string $hqId, ?string $driverId): ?DriverRecord;

    public function lockWithCapabilities(?string $hqId, string $driverId): ?DriverRecord;

    public function userIdOf(?string $hqId, ?string $driverId): ?string;

    public function linkedToAnotherDriver(string $userId, ?string $currentDriverId): bool;

    public function hasCapability(?string $hqId, ?string $driverId, string $capability): bool;

    /** A driver who may take on new work at a Node: active, available and holding the capability. */
    public function eligibleAtNode(?string $hqId, ?string $driverId, string $nodeId, string $capability): bool;

    /** @return Collection<int, DriverRecord> */
    public function availableAtNode(?string $hqId, string $nodeId, ?string $capability): Collection;

    /** @param list<string> $visibleNodeIds @return LengthAwarePaginator<DriverRecord> */
    public function search(?string $hqId, array $visibleNodeIds, FleetFiltersDto $filters): LengthAwarePaginator;

    /** Sends an available driver on mission; a driver already on one is left untouched. @param array<string, mixed> $changes */
    public function startMission(?string $hqId, ?string $driverId, array $changes): void;

    /** @param array<string, mixed> $changes */
    public function endMission(?string $hqId, ?string $driverId, array $changes): void;

    /** @param list<string> $driverIds @param array<string, mixed> $changes */
    public function endMissions(?string $hqId, array $driverIds, array $changes): void;

    public function replaceCapabilities(string $driverId, array $rows): void;
}
