<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Dto\FleetFiltersDto;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Infrastructure\Persistence\Models\DriverCapabilityRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final class EloquentDriverRepository implements DriverRepositoryInterface
{
    /** Rows written per statement when a driver's capabilities are rewritten. */
    private const BATCH_SIZE = 100;

    public function findByTenant(?string $hqId, ?string $driverId): ?DriverRecord
    {
        return DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->first();
    }

    public function findWithCapabilities(?string $hqId, string $driverId): ?DriverRecord
    {
        return DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->with('capabilities')->first();
    }

    public function lockByTenant(?string $hqId, ?string $driverId): ?DriverRecord
    {
        return DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->lockForUpdate()->first();
    }

    public function lockWithCapabilities(?string $hqId, string $driverId): ?DriverRecord
    {
        return DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->lockForUpdate()->with('capabilities')->first();
    }

    public function userIdOf(?string $hqId, ?string $driverId): ?string
    {
        $userId = DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->value('user_id');

        return $userId === null ? null : (string) $userId;
    }

    public function linkedToAnotherDriver(string $userId, ?string $currentDriverId): bool
    {
        return DriverRecord::query()->where('user_id', $userId)
            ->when($currentDriverId !== null, fn ($query) => $query->where('driver_id', '!=', $currentDriverId))->exists();
    }

    public function hasCapability(?string $hqId, ?string $driverId, string $capability): bool
    {
        return DriverCapabilityRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'capability' => $capability])->exists();
    }

    public function eligibleAtNode(?string $hqId, ?string $driverId, string $nodeId, string $capability): bool
    {
        return DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'home_node_id' => $nodeId,
            'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE'])
            ->whereHas('capabilities', fn ($capabilities) => $capabilities->where('capability', $capability))->exists();
    }

    public function availableAtNode(?string $hqId, string $nodeId, ?string $capability): Collection
    {
        return DriverRecord::query()->where(['hq_id' => $hqId, 'home_node_id' => $nodeId])
            ->where('status', FleetStatus::Active)->where('availability_status', FleetAvailability::Available)
            ->when($capability !== null, fn ($query) => $query->whereHas('capabilities', fn ($capabilities) => $capabilities->where('capability', $capability)))
            ->with('capabilities')->orderBy('driver_code')->get();
    }

    public function search(?string $hqId, array $visibleNodeIds, FleetFiltersDto $filters): LengthAwarePaginator
    {
        $query = DriverRecord::query()->where('hq_id', $hqId)->whereIn('home_node_id', $visibleNodeIds);
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }
        if ($filters->availabilityStatus !== null) {
            $query->where('availability_status', $filters->availabilityStatus);
        }
        if ($filters->homeNodeId !== null && $filters->homeNodeId !== '') {
            $query->where('home_node_id', $filters->homeNodeId);
        }
        $query->with('capabilities');
        if ($filters->unlinked) {
            $query->whereNull('user_id');
        }
        if ($filters->capability !== null) {
            $query->whereHas('capabilities', fn ($capabilities) => $capabilities->where('capability', $filters->capability));
        }
        if ($filters->search !== '') {
            $search = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(fn ($match) => $match->where('driver_code', 'like', $search)->orWhere('display_name', 'like', $search)->orWhere('mobile', 'like', $search));
        }

        return $query->orderBy('driver_code')->paginate(perPage: $filters->perPage, page: $filters->page);
    }

    public function startMission(?string $hqId, ?string $driverId, array $changes): void
    {
        DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'availability_status' => 'AVAILABLE'])->update($changes);
    }

    public function endMission(?string $hqId, ?string $driverId, array $changes): void
    {
        DriverRecord::query()->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'availability_status' => 'ON_MISSION'])->update($changes);
    }

    public function endMissions(?string $hqId, array $driverIds, array $changes): void
    {
        DriverRecord::query()->where(['hq_id' => $hqId, 'availability_status' => 'ON_MISSION'])
            ->whereIn('driver_id', array_values(array_unique($driverIds)))->update($changes);
    }

    public function replaceCapabilities(string $driverId, array $rows): void
    {
        DriverCapabilityRecord::query()->where('driver_id', $driverId)->delete();
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            DriverCapabilityRecord::query()->insert($chunk);
        }
    }
}
