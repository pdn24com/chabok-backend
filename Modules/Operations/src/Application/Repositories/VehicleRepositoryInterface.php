<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Dto\FleetFiltersDto;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

interface VehicleRepositoryInterface
{
    public function findByTenant(?string $hqId, string $vehicleId): ?VehicleRecord;

    public function lockByTenant(?string $hqId, string $vehicleId): ?VehicleRecord;

    /** @return Collection<int, VehicleRecord> */
    public function availableAtNode(?string $hqId, string $nodeId): Collection;

    /** @param list<string> $visibleNodeIds @return LengthAwarePaginator<VehicleRecord> */
    public function search(?string $hqId, array $visibleNodeIds, FleetFiltersDto $filters): LengthAwarePaginator;
}
