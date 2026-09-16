<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Modules\Foundation\Application\Data\Page;
use Modules\Operations\Application\Repositories\FleetRepository;
use Modules\Operations\Domain\FleetWriteConflict;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final class EloquentFleetRepository implements FleetRepository
{
    private function applyCommonFilters($query, array $filters, string $alias): void
    {
        foreach (['status', 'availability_status', 'home_node_id'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where("{$alias}.{$field}", $filters[$field]);
            }
        }
    }

    public function paginateDrivers(?string $hqId, array $visibleNodes, array $filters): Page
    {
        $query = DriverRecord::query()->toBase()->from('drivers as d')->where('d.hq_id', $hqId)->whereIn('d.home_node_id', $visibleNodes);
        $this->applyCommonFilters($query, $filters, 'd');
        if ($filters['unlinked'] ?? false) {
            $query->whereNull('d.user_id');
        }
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes(trim((string) $filters['search']), '%_\\') . '%';
            $query->where(fn($q) => $q->where('d.driver_code', 'like', $search)->orWhere('d.display_name', 'like', $search)->orWhere('d.mobile', 'like', $search));
        }
        if (($filters['capability'] ?? '') !== '') {
            $query->whereExists(fn($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', $filters['capability']));
        }
        $page = $query->orderBy('d.driver_code')->paginate(perPage: min(100, max(1, (int) ($filters['per_page'] ?? 20))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function paginateVehicles(?string $hqId, array $visibleNodes, array $filters): Page
    {
        $query = VehicleRecord::query()->toBase()->from('vehicles as v')->where('v.hq_id', $hqId)->whereIn('v.home_node_id', $visibleNodes);
        $this->applyCommonFilters($query, $filters, 'v');
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes(trim((string) $filters['search']), '%_\\') . '%';
            $query->where(fn($q) => $q->where('v.vehicle_code', 'like', $search)->orWhere('v.plate_number', 'like', $search));
        }
        if (($filters['vehicle_type'] ?? '') !== '') {
            $query->where('v.vehicle_type', $filters['vehicle_type']);
        }
        $page = $query->orderBy('v.vehicle_code')->paginate(perPage: min(100, max(1, (int) ($filters['per_page'] ?? 20))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function capabilitiesForDrivers(array $ids): array
    {
        return DB::table('driver_capabilities')->whereIn('driver_id', $ids)->orderByRaw("CASE capability WHEN 'PICKUP' THEN 1 WHEN 'LINEHAUL' THEN 2 WHEN 'DELIVERY' THEN 3 END")->get()->groupBy('driver_id')->map(fn($rows) => $rows->pluck('capability')->map(fn($value): string => (string) $value)->all())->all();
    }

    public function findDriver(?string $hqId, string $driverId): ?object
    {
        return DriverRecord::query()->toBase()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->first();
    }

    public function findDriverForUpdate(?string $hqId, string $driverId): ?object
    {
        return DriverRecord::query()->toBase()->where(['hq_id' => $hqId, 'driver_id' => $driverId])->lockForUpdate()->first();
    }

    public function insertDriver(array $attributes): void
    {
        try {
            DriverRecord::query()->toBase()->insert($attributes);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new FleetWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function updateDriver(?string $hqId, string $driverId, int $expected, array $attributes): void
    {
        try {
            DriverRecord::query()->toBase()->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'version' => $expected])->update($attributes);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new FleetWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function findVehicle(?string $hqId, string $vehicleId): ?object
    {
        return VehicleRecord::query()->toBase()->where(['hq_id' => $hqId, 'vehicle_id' => $vehicleId])->first();
    }

    public function findVehicleForUpdate(?string $hqId, string $vehicleId): ?object
    {
        return VehicleRecord::query()->toBase()->where(['hq_id' => $hqId, 'vehicle_id' => $vehicleId])->lockForUpdate()->first();
    }

    public function insertVehicle(array $attributes): void
    {
        try {
            VehicleRecord::query()->toBase()->insert($attributes);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new FleetWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function updateVehicle(?string $hqId, string $vehicleId, int $expected, array $attributes): void
    {
        try {
            VehicleRecord::query()->toBase()->where(['hq_id' => $hqId, 'vehicle_id' => $vehicleId, 'version' => $expected])->update($attributes);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new FleetWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function driverCapabilities(string $driverId): array
    {
        return DB::table('driver_capabilities')->where('driver_id', $driverId)->orderByRaw("CASE capability WHEN 'PICKUP' THEN 1 WHEN 'LINEHAUL' THEN 2 WHEN 'DELIVERY' THEN 3 END")->pluck('capability')->map(fn($value): string => (string) $value)->all();
    }

    public function activeNodeExists(?string $hqId, string $nodeId): bool
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->exists();
    }

    public function tenantUserExists(?string $hqId, mixed $userId): bool
    {
        return DB::table('users')->where(['hq_id' => $hqId, 'user_id' => $userId])->exists();
    }

    public function userHasDriver(mixed $userId, ?string $currentDriverId): bool
    {
        $query = DriverRecord::query()->toBase()->where('user_id', $userId);
        if ($currentDriverId !== null) {
            $query->where('driver_id', '!=', $currentDriverId);
        }
        return $query->exists();
    }

    public function removeCapabilities(string $driverId): void
    {
        try {
            DB::table('driver_capabilities')->where('driver_id', $driverId)->delete();
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new FleetWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }

    public function insertCapability(array $attributes): void
    {
        try {
            DB::table('driver_capabilities')->insert($attributes);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new FleetWriteConflict(previous: $exception);
            }
            throw $exception;
        }
    }
}
