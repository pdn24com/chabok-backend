<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Dto\FleetFiltersDto;
use Modules\Operations\Application\Repositories\VehicleRepositoryInterface;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final class EloquentVehicleRepository implements VehicleRepositoryInterface
{
    public function findByTenant(?string $hqId, string $vehicleId): ?VehicleRecord
    {
        return VehicleRecord::query()->where(['hq_id' => $hqId, 'vehicle_id' => $vehicleId])->first();
    }

    public function lockByTenant(?string $hqId, string $vehicleId): ?VehicleRecord
    {
        return VehicleRecord::query()->where(['hq_id' => $hqId, 'vehicle_id' => $vehicleId])->lockForUpdate()->first();
    }

    public function availableAtNode(?string $hqId, string $nodeId): Collection
    {
        return VehicleRecord::query()->where(['hq_id' => $hqId, 'home_node_id' => $nodeId])
            ->where('status', FleetStatus::Active)->where('availability_status', FleetAvailability::Available)
            ->orderBy('vehicle_code')->get();
    }

    public function search(?string $hqId, array $visibleNodeIds, FleetFiltersDto $filters): LengthAwarePaginator
    {
        $query = VehicleRecord::query()->where('hq_id', $hqId)->whereIn('home_node_id', $visibleNodeIds);
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }
        if ($filters->availabilityStatus !== null) {
            $query->where('availability_status', $filters->availabilityStatus);
        }
        if ($filters->homeNodeId !== null && $filters->homeNodeId !== '') {
            $query->where('home_node_id', $filters->homeNodeId);
        }
        if ($filters->vehicleType !== null) {
            $query->where('vehicle_type', $filters->vehicleType);
        }
        if ($filters->search !== '') {
            $search = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(fn ($match) => $match->where('vehicle_code', 'like', $search)->orWhere('plate_number', 'like', $search));
        }

        return $query->orderBy('vehicle_code')->paginate(perPage: $filters->perPage, page: $filters->page);
    }
}
