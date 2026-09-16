<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationalDirectoryService
{
    public function __construct(private AuthorizationContextResolver $authorization) {}

    /** @return list<array<string, mixed>> */
    public function drivers(AuthenticatedPrincipal $actor, string $nodeId, ?string $capability): array
    {
        $this->access($actor, $nodeId, 'driver.view', 'Driver');
        $query = DB::table('drivers as d')->where(['d.hq_id' => $actor->hqId, 'd.home_node_id' => $nodeId])
            ->where('d.status', 'ACTIVE')->where('d.availability_status', 'AVAILABLE');
        if ($capability !== null) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('driver_capabilities as dc')
                ->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', $capability));
        }
        return $query->orderBy('d.driver_code')->get()->map(function ($driver): array {
            $capabilities = DB::table('driver_capabilities')->where('driver_id', $driver->driver_id)
                ->orderBy('capability')->pluck('capability')->map(fn ($v): string => (string) $v)->all();
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

    /** @return list<array<string, mixed>> */
    public function vehicles(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'driver.view', 'Driver');
        return DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'home_node_id' => $nodeId])
            ->where('status', 'ACTIVE')->where('availability_status', 'AVAILABLE')->orderBy('vehicle_code')
            ->get()->map(fn ($vehicle): array => [
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

    /** @return list<array<string, mixed>> */
    public function routes(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'live_operations.view', 'LiveOperations');
        return DB::table('route_definitions')->where(['hq_id' => $actor->hqId, 'status' => 'ACTIVE'])
            ->orderBy('route_code')->get()->map(function ($route): array {
                $legs = DB::table('route_definition_legs as l')
                    ->join('nodes as o', 'o.node_id', '=', 'l.origin_node_id')
                    ->join('nodes as d', 'd.node_id', '=', 'l.destination_node_id')
                    ->where('l.route_definition_id', $route->route_definition_id)->where('l.status', 'ACTIVE')
                    ->orderBy('l.leg_order')->get(['l.*', 'o.node_code as origin_code', 'o.node_title as origin_title', 'd.node_code as destination_code', 'd.node_title as destination_title'])
                    ->map(fn ($leg): array => [
                        'route_definition_leg_id' => (string) $leg->route_definition_leg_id,
                        'leg_order' => (int) $leg->leg_order,
                        'origin_node' => ['node_id' => (string) $leg->origin_node_id, 'node_code' => (string) $leg->origin_code, 'node_title' => (string) $leg->origin_title],
                        'destination_node' => ['node_id' => (string) $leg->destination_node_id, 'node_code' => (string) $leg->destination_code, 'node_title' => (string) $leg->destination_title],
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

    /** @param list<array{origin_node_id:string,destination_node_id:string}> $legs */
    public function createRoute(string $hqId, string $code, string $title, array $legs): string
    {
        if ($legs === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'A route requires at least one leg.');
        }
        $seen = []; $previousDestination = null;
        foreach ($legs as $index => $leg) {
            if ($previousDestination !== null && $leg['origin_node_id'] !== $previousDestination) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The route chain is broken.');
            }
            foreach (['origin_node_id', 'destination_node_id'] as $field) {
                $node = DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $leg[$field], 'status' => 'ACTIVE'])->first();
                if ($node === null) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every route node must be active and belong to the tenant.');
                }
            }
            if ($leg['origin_node_id'] === $leg['destination_node_id'] || isset($seen[$leg['destination_node_id']])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Pilot routes cannot contain cycles.');
            }
            $seen[$leg['origin_node_id']] = true;
            $previousDestination = $leg['destination_node_id'];
        }
        return DB::transaction(function () use ($hqId, $code, $title, $legs): string {
            $id = (string) Str::uuid();
            DB::table('route_definitions')->insert([
                'route_definition_id' => $id, 'hq_id' => $hqId, 'route_code' => $code,
                'route_title' => $title, 'status' => 'ACTIVE', 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($legs as $index => $leg) {
                DB::table('route_definition_legs')->insert([
                    'route_definition_leg_id' => (string) Str::uuid(), 'hq_id' => $hqId,
                    'route_definition_id' => $id, 'leg_order' => $index + 1,
                    'origin_node_id' => $leg['origin_node_id'], 'destination_node_id' => $leg['destination_node_id'],
                    'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $id;
        });
    }

    private function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission, string $module): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($e) => $e['module_code'] === $module && $e['status'] === 'ENABLED')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        if (! in_array($nodeId, \Modules\Foundation\Application\ScopedAccess::nodes($context, $permission), true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
    }
}
