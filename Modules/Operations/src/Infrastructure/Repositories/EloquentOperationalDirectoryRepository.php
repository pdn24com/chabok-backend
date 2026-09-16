<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Operations\Application\Repositories\OperationalDirectoryRepository;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionLegRecord;

final class EloquentOperationalDirectoryRepository implements OperationalDirectoryRepository
{
    public function drivers(?string $hqId, string $nodeId, ?string $capability): array
    {
        $query = DriverRecord::query()->toBase()->from('drivers as d')->where(['d.hq_id' => $hqId, 'd.home_node_id' => $nodeId])->where('d.status', 'ACTIVE')->where('d.availability_status', 'AVAILABLE');
        if ($capability !== null) {
            $query->whereExists(fn($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', $capability));
        }
        return $query->orderBy('d.driver_code')->get()->map(function ($driver): array {
            $capabilities = DB::table('driver_capabilities')->where('driver_id', $driver->driver_id)->orderBy('capability')->pluck('capability')->map(fn($v): string => (string) $v)->all();
            return [
                'driver_id' => (string) $driver->driver_id,
                'driver_code' => (string) $driver->driver_code,
                'display_name' => (string) $driver->display_name,
                'home_node_id' => (string) $driver->home_node_id,
                'operational_type' => (string) $driver->operational_type,
                'status' => (string) $driver->status,
                'availability_status' => (string) $driver->availability_status,
                'capabilities' => $capabilities,
                'version' => (int) $driver->version,
            ];
        })->all();
    }

    public function vehicles(?string $hqId, string $nodeId): array
    {
        return VehicleRecord::query()->toBase()->where(['hq_id' => $hqId, 'home_node_id' => $nodeId])->where('status', 'ACTIVE')->where('availability_status', 'AVAILABLE')->orderBy('vehicle_code')->get()->map(fn($vehicle): array => [
            'vehicle_id' => (string) $vehicle->vehicle_id,
            'vehicle_code' => (string) $vehicle->vehicle_code,
            'registration_number' => (string) $vehicle->registration_number,
            'vehicle_type' => (string) $vehicle->vehicle_type,
            'home_node_id' => (string) $vehicle->home_node_id,
            'status' => (string) $vehicle->status,
            'availability_status' => (string) $vehicle->availability_status,
            'version' => (int) $vehicle->version,
        ])->all();
    }

    public function routes(?string $hqId): array
    {
        return RouteDefinitionRecord::query()->toBase()->where(['hq_id' => $hqId, 'status' => 'ACTIVE'])->orderBy('route_code')->get()->map(function ($route): array {
            $legs = RouteDefinitionLegRecord::query()->toBase()->from('route_definition_legs as l')->join('nodes as o', 'o.node_id', '=', 'l.origin_node_id')->join('nodes as d', 'd.node_id', '=', 'l.destination_node_id')->where('l.route_definition_id', $route->route_definition_id)->where('l.status', 'ACTIVE')->orderBy('l.leg_order')->get([
                'l.*',
                'o.node_code as origin_code',
                'o.node_title as origin_title',
                'd.node_code as destination_code',
                'd.node_title as destination_title',
            ])->map(fn($leg): array => [
                'route_definition_leg_id' => (string) $leg->route_definition_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node' => [
                    'node_id' => (string) $leg->origin_node_id,
                    'node_code' => (string) $leg->origin_code,
                    'node_title' => (string) $leg->origin_title,
                ],
                'destination_node' => [
                    'node_id' => (string) $leg->destination_node_id,
                    'node_code' => (string) $leg->destination_code,
                    'node_title' => (string) $leg->destination_title,
                ],
            ])->all();
            return [
                'route_definition_id' => (string) $route->route_definition_id,
                'route_code' => (string) $route->route_code,
                'route_title' => (string) $route->route_title,
                'status' => (string) $route->status,
                'version' => (int) $route->version,
                'legs' => $legs,
            ];
        })->all();
    }

    public function activeNode(string $hqId, string $nodeId): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->first();
    }

    public function insertRoute(array $attributes): void
    {
        RouteDefinitionRecord::query()->toBase()->insert($attributes);
    }

    public function insertLeg(array $attributes): void
    {
        RouteDefinitionLegRecord::query()->toBase()->insert($attributes);
    }
}
