<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final class ManifestOperationalContext
{
    /** @return array<string, mixed> */
    public function options(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $node = DB::table('nodes')->where([
            'hq_id' => $actor->hqId,
            'node_id' => $nodeId,
            'status' => 'ACTIVE',
        ])->first();
        if ($node === null) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }

        return [
            'current_node' => $this->nodeResource($node),
            'contexts' => [
                $this->pickupReceptionOption($node),
                ...$this->transportReceptionOptions((string) $actor->hqId, $nodeId),
                ...$this->routeOutboundOptions((string) $actor->hqId, $nodeId),
                $this->deliveryAssignmentOption($node),
            ],
            'drivers' => $this->drivers((string) $actor->hqId, $nodeId),
            'vehicles' => $this->vehicles((string) $actor->hqId, $nodeId),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function normalize(AuthenticatedPrincipal $actor, string $nodeId, array $input): array
    {
        $target = (string) $input['manifest_status'];

        return match ($target) {
            'IR' => ($input['transport_run_id'] ?? null) === null
                ? $this->pickupReception((string) $actor->hqId, $nodeId, $input)
                : $this->transportReception((string) $actor->hqId, $nodeId, $input),
            'OF' => $this->routeOutbound((string) $actor->hqId, $nodeId, $input),
            'OD' => $this->deliveryAssignment((string) $actor->hqId, $nodeId, $input),
            default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Manifest target status is not available.'),
        };
    }

    /** @return array<string, mixed> */
    public function summary(object $manifest): array
    {
        $nodeIds = array_values(array_unique(array_filter([
            (string) $manifest->node_id,
            $manifest->origin_node_id === null ? null : (string) $manifest->origin_node_id,
            $manifest->destination_node_id === null ? null : (string) $manifest->destination_node_id,
        ])));
        $nodes = DB::table('nodes')->where('hq_id', $manifest->hq_id)->whereIn('node_id', $nodeIds)
            ->get()->keyBy('node_id');

        $plan = $manifest->route_plan_id === null ? null : DB::table('route_plans as rp')
            ->join('consignments as c', function ($join): void {
                $join->on('c.consignment_id', '=', 'rp.consignment_id')->on('c.hq_id', '=', 'rp.hq_id');
            })
            ->join('route_definitions as rd', function ($join): void {
                $join->on('rd.route_definition_id', '=', 'rp.route_definition_id')->on('rd.hq_id', '=', 'rp.hq_id');
            })
            ->where(['rp.hq_id' => $manifest->hq_id, 'rp.route_plan_id' => $manifest->route_plan_id])
            ->first(['rp.*', 'c.consignment_number', 'rd.route_code', 'rd.route_title']);
        $leg = $manifest->route_plan_leg_id === null ? null : DB::table('route_plan_legs')->where([
            'hq_id' => $manifest->hq_id,
            'route_plan_leg_id' => $manifest->route_plan_leg_id,
        ])->first();
        $run = $manifest->transport_run_id === null ? null : DB::table('transport_runs')->where([
            'hq_id' => $manifest->hq_id,
            'transport_run_id' => $manifest->transport_run_id,
        ])->first();
        $driver = $manifest->assigned_driver_id === null ? null : DB::table('drivers')->where([
            'hq_id' => $manifest->hq_id,
            'driver_id' => $manifest->assigned_driver_id,
        ])->first();
        $vehicle = $manifest->assigned_vehicle_id === null ? null : DB::table('vehicles')->where([
            'hq_id' => $manifest->hq_id,
            'vehicle_id' => $manifest->assigned_vehicle_id,
        ])->first();

        return [
            'operational_context_type' => (string) $manifest->operational_context_type,
            'current_node' => isset($nodes[$manifest->node_id]) ? $this->nodeResource($nodes[$manifest->node_id]) : null,
            'origin_node' => $manifest->origin_node_id !== null && isset($nodes[$manifest->origin_node_id]) ? $this->nodeResource($nodes[$manifest->origin_node_id]) : null,
            'destination_node' => $manifest->destination_node_id !== null && isset($nodes[$manifest->destination_node_id]) ? $this->nodeResource($nodes[$manifest->destination_node_id]) : null,
            'route_plan' => $plan === null ? null : [
                'route_plan_id' => (string) $plan->route_plan_id,
                'consignment_id' => (string) $plan->consignment_id,
                'consignment_number' => (string) $plan->consignment_number,
                'route_definition_id' => (string) $plan->route_definition_id,
                'route_code' => (string) $plan->route_code,
                'route_title' => (string) $plan->route_title,
                'status' => (string) $plan->status,
                'version' => (int) $plan->version,
            ],
            'route_leg' => $leg === null ? null : [
                'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'status' => (string) $leg->status,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ],
            'transport_run' => $run === null ? null : [
                'transport_run_id' => (string) $run->transport_run_id,
                'transport_run_number' => (string) $run->transport_run_number,
                'status' => (string) $run->status,
                'version' => (int) $run->version,
            ],
            'driver' => $driver === null ? null : $this->driverResource($driver),
            'vehicle' => $vehicle === null ? null : $this->vehicleResource($vehicle),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function pickupReception(string $hqId, string $nodeId, array $input): array
    {
        $this->assertNull($input, ['origin_node_id', 'route_plan_id', 'route_plan_leg_id', 'transport_run_id', 'assigned_driver_id', 'assigned_vehicle_id']);
        $this->assertMatches($input, 'destination_node_id', $nodeId);
        $this->activeNode($hqId, $nodeId);

        return [
            'manifest_status' => 'IR',
            'manifest_type' => 'INBOUND_RECEPTION',
            'operational_context_type' => 'PICKUP_RECEPTION',
            'origin_node_id' => null,
            'destination_node_id' => $nodeId,
            'route_plan_id' => null,
            'route_plan_leg_id' => null,
            'transport_run_id' => null,
            'assigned_driver_id' => null,
            'assigned_vehicle_id' => null,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function transportReception(string $hqId, string $nodeId, array $input): array
    {
        $run = DB::table('transport_runs as tr')
            ->join('route_plan_legs as rpl', function ($join): void {
                $join->on('rpl.route_plan_leg_id', '=', 'tr.route_plan_leg_id')->on('rpl.hq_id', '=', 'tr.hq_id');
            })
            ->where([
                'tr.hq_id' => $hqId,
                'tr.transport_run_id' => $input['transport_run_id'],
                'tr.destination_node_id' => $nodeId,
                'tr.status' => 'ARRIVED',
                'rpl.status' => 'ARRIVED',
            ])->first(['tr.*', 'rpl.route_plan_id']);
        if ($run === null) {
            throw $this->contextError('transport_run_id', 'The selected Transport Run is not an arrived run for this node.');
        }
        $this->assertMatches($input, 'origin_node_id', (string) $run->origin_node_id);
        $this->assertMatches($input, 'destination_node_id', $nodeId);
        $this->assertMatches($input, 'route_plan_id', (string) $run->route_plan_id);
        $this->assertMatches($input, 'route_plan_leg_id', (string) $run->route_plan_leg_id);
        $this->assertMatches($input, 'assigned_driver_id', (string) $run->driver_id);
        $this->assertMatches($input, 'assigned_vehicle_id', (string) $run->vehicle_id);

        return [
            'manifest_status' => 'IR',
            'manifest_type' => 'INBOUND_RECEPTION',
            'operational_context_type' => 'TRANSPORT_RECEPTION',
            'origin_node_id' => (string) $run->origin_node_id,
            'destination_node_id' => $nodeId,
            'route_plan_id' => (string) $run->route_plan_id,
            'route_plan_leg_id' => (string) $run->route_plan_leg_id,
            'transport_run_id' => (string) $run->transport_run_id,
            'assigned_driver_id' => (string) $run->driver_id,
            'assigned_vehicle_id' => (string) $run->vehicle_id,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function routeOutbound(string $hqId, string $nodeId, array $input): array
    {
        if (($input['route_plan_leg_id'] ?? null) === null) {
            throw $this->contextError('route_plan_leg_id', 'A routed Route Plan Leg is required for OF.');
        }
        $leg = DB::table('route_plan_legs as rpl')
            ->join('route_plans as rp', function ($join): void {
                $join->on('rp.route_plan_id', '=', 'rpl.route_plan_id')->on('rp.hq_id', '=', 'rpl.hq_id');
            })
            ->join('route_definition_versions as rdv', function ($join): void {
                $join->on('rdv.route_definition_version_id', '=', 'rp.route_definition_version_id')->on('rdv.hq_id', '=', 'rp.hq_id');
            })
            ->where([
                'rpl.hq_id' => $hqId,
                'rpl.route_plan_leg_id' => $input['route_plan_leg_id'],
                'rpl.origin_node_id' => $nodeId,
                'rpl.status' => 'ROUTED',
                'rp.status' => 'IN_PROGRESS',
            ])->whereIn('rdv.status', ['PUBLISHED', 'SUPERSEDED'])
            ->first(['rpl.*', 'rp.route_plan_id']);
        if ($leg === null) {
            throw $this->contextError('route_plan_leg_id', 'The selected Route Plan Leg is not routed from this node or its configuration version is unavailable.');
        }
        $this->assertMatches($input, 'route_plan_id', (string) $leg->route_plan_id);
        $this->assertMatches($input, 'origin_node_id', $nodeId);
        $this->assertMatches($input, 'destination_node_id', (string) $leg->destination_node_id);
        $this->assertNull($input, ['transport_run_id', 'assigned_driver_id', 'assigned_vehicle_id']);

        return [
            'manifest_status' => 'OF',
            'manifest_type' => 'OUTBOUND_TRANSFER',
            'operational_context_type' => 'ROUTE_OUTBOUND',
            'origin_node_id' => $nodeId,
            'destination_node_id' => (string) $leg->destination_node_id,
            'route_plan_id' => (string) $leg->route_plan_id,
            'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
            'transport_run_id' => null,
            'assigned_driver_id' => null,
            'assigned_vehicle_id' => null,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function deliveryAssignment(string $hqId, string $nodeId, array $input): array
    {
        $driverId = $input['assigned_driver_id'] ?? null;
        if (! is_string($driverId) || ! $this->deliveryDriverExists($hqId, $nodeId, $driverId)) {
            throw $this->contextError('assigned_driver_id', 'An active available delivery Driver at this node is required.');
        }
        $vehicleId = $input['assigned_vehicle_id'] ?? null;
        if ($vehicleId !== null && ! DB::table('vehicles')->where([
            'hq_id' => $hqId,
            'vehicle_id' => $vehicleId,
            'home_node_id' => $nodeId,
            'status' => 'ACTIVE',
            'availability_status' => 'AVAILABLE',
        ])->exists()) {
            throw $this->contextError('assigned_vehicle_id', 'The selected Vehicle is not active and available at this node.');
        }
        $this->assertMatches($input, 'origin_node_id', $nodeId);
        $this->assertNull($input, ['destination_node_id', 'route_plan_id', 'route_plan_leg_id', 'transport_run_id']);

        return [
            'manifest_status' => 'OD',
            'manifest_type' => 'DELIVERY_ASSIGNMENT',
            'operational_context_type' => 'DELIVERY_ASSIGNMENT',
            'origin_node_id' => $nodeId,
            'destination_node_id' => null,
            'route_plan_id' => null,
            'route_plan_leg_id' => null,
            'transport_run_id' => null,
            'assigned_driver_id' => $driverId,
            'assigned_vehicle_id' => $vehicleId,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function transportReceptionOptions(string $hqId, string $nodeId): array
    {
        return DB::table('transport_runs as tr')
            ->join('route_plan_legs as rpl', function ($join): void {
                $join->on('rpl.route_plan_leg_id', '=', 'tr.route_plan_leg_id')->on('rpl.hq_id', '=', 'tr.hq_id');
            })
            ->join('route_plans as rp', function ($join): void {
                $join->on('rp.route_plan_id', '=', 'rpl.route_plan_id')->on('rp.hq_id', '=', 'rpl.hq_id');
            })
            ->join('consignments as c', function ($join): void {
                $join->on('c.consignment_id', '=', 'rp.consignment_id')->on('c.hq_id', '=', 'rp.hq_id');
            })
            ->join('nodes as origin', function ($join): void {
                $join->on('origin.node_id', '=', 'tr.origin_node_id')->on('origin.hq_id', '=', 'tr.hq_id');
            })
            ->join('nodes as destination', function ($join): void {
                $join->on('destination.node_id', '=', 'tr.destination_node_id')->on('destination.hq_id', '=', 'tr.hq_id');
            })
            ->where(['tr.hq_id' => $hqId, 'tr.destination_node_id' => $nodeId, 'tr.status' => 'ARRIVED', 'rpl.status' => 'ARRIVED'])
            ->orderByDesc('tr.arrived_at')->get([
                'tr.*', 'rpl.route_plan_id', 'rpl.leg_order', 'c.consignment_number',
                'origin.node_code as origin_code', 'origin.node_title as origin_title',
                'destination.node_code as destination_code', 'destination.node_title as destination_title',
            ])->map(fn (object $row): array => [
                'context_key' => 'TRANSPORT_RECEPTION:'.$row->transport_run_id,
                'operational_context_type' => 'TRANSPORT_RECEPTION',
                'manifest_status' => 'IR',
                'label' => ['fa' => "ورود {$row->transport_run_number} · {$row->origin_title} ← {$row->destination_title}", 'en' => "Arrival {$row->transport_run_number} · {$row->origin_title} to {$row->destination_title}"],
                'selection' => [
                    'manifest_status' => 'IR', 'origin_node_id' => (string) $row->origin_node_id,
                    'destination_node_id' => (string) $row->destination_node_id,
                    'route_plan_id' => (string) $row->route_plan_id,
                    'route_plan_leg_id' => (string) $row->route_plan_leg_id,
                    'transport_run_id' => (string) $row->transport_run_id,
                    'assigned_driver_id' => (string) $row->driver_id,
                    'assigned_vehicle_id' => (string) $row->vehicle_id,
                ],
                'consignment_number' => (string) $row->consignment_number,
                'route_leg_order' => (int) $row->leg_order,
                'transport_run_number' => (string) $row->transport_run_number,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function routeOutboundOptions(string $hqId, string $nodeId): array
    {
        return DB::table('route_plan_legs as rpl')
            ->join('route_plans as rp', function ($join): void {
                $join->on('rp.route_plan_id', '=', 'rpl.route_plan_id')->on('rp.hq_id', '=', 'rpl.hq_id');
            })
            ->join('route_definition_versions as rdv', function ($join): void {
                $join->on('rdv.route_definition_version_id', '=', 'rp.route_definition_version_id')->on('rdv.hq_id', '=', 'rp.hq_id');
            })
            ->join('route_definitions as rd', function ($join): void {
                $join->on('rd.route_definition_id', '=', 'rp.route_definition_id')->on('rd.hq_id', '=', 'rp.hq_id');
            })
            ->join('consignments as c', function ($join): void {
                $join->on('c.consignment_id', '=', 'rp.consignment_id')->on('c.hq_id', '=', 'rp.hq_id');
            })
            ->join('nodes as destination', function ($join): void {
                $join->on('destination.node_id', '=', 'rpl.destination_node_id')->on('destination.hq_id', '=', 'rpl.hq_id');
            })
            ->where(['rpl.hq_id' => $hqId, 'rpl.origin_node_id' => $nodeId, 'rpl.status' => 'ROUTED', 'rp.status' => 'IN_PROGRESS'])
            ->whereIn('rdv.status', ['PUBLISHED', 'SUPERSEDED'])
            ->orderBy('rd.route_code')->orderBy('c.consignment_number')->get([
                'rpl.*', 'rp.route_plan_id', 'c.consignment_number', 'rd.route_code', 'rd.route_title',
                'destination.node_code as destination_code', 'destination.node_title as destination_title',
            ])->map(fn (object $row): array => [
                'context_key' => 'ROUTE_OUTBOUND:'.$row->route_plan_leg_id,
                'operational_context_type' => 'ROUTE_OUTBOUND',
                'manifest_status' => 'OF',
                'label' => ['fa' => "{$row->consignment_number} · {$row->route_title} · گام {$row->leg_order} به {$row->destination_title}", 'en' => "{$row->consignment_number} · {$row->route_title} · leg {$row->leg_order} to {$row->destination_title}"],
                'selection' => [
                    'manifest_status' => 'OF', 'origin_node_id' => $nodeId,
                    'destination_node_id' => (string) $row->destination_node_id,
                    'route_plan_id' => (string) $row->route_plan_id,
                    'route_plan_leg_id' => (string) $row->route_plan_leg_id,
                    'transport_run_id' => null, 'assigned_driver_id' => null, 'assigned_vehicle_id' => null,
                ],
                'consignment_number' => (string) $row->consignment_number,
                'route_code' => (string) $row->route_code,
                'route_leg_order' => (int) $row->leg_order,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function drivers(string $hqId, string $nodeId): array
    {
        return DB::table('drivers as d')->where([
            'd.hq_id' => $hqId, 'd.home_node_id' => $nodeId,
            'd.status' => 'ACTIVE', 'd.availability_status' => 'AVAILABLE',
        ])->whereExists(fn (Builder $capability) => $capability->selectRaw('1')->from('driver_capabilities as dc')
            ->whereColumn('dc.driver_id', 'd.driver_id')->whereColumn('dc.hq_id', 'd.hq_id')->where('dc.capability', 'DELIVERY'))
            ->orderBy('d.driver_code')->get()->map(fn (object $driver): array => $this->driverResource($driver))->all();
    }

    /** @return list<array<string, mixed>> */
    private function vehicles(string $hqId, string $nodeId): array
    {
        return DB::table('vehicles')->where([
            'hq_id' => $hqId, 'home_node_id' => $nodeId,
            'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE',
        ])->orderBy('vehicle_code')->get()->map(fn (object $vehicle): array => $this->vehicleResource($vehicle))->all();
    }

    /** @return array<string, mixed> */
    private function pickupReceptionOption(object $node): array
    {
        return [
            'context_key' => 'PICKUP_RECEPTION',
            'operational_context_type' => 'PICKUP_RECEPTION',
            'manifest_status' => 'IR',
            'label' => ['fa' => 'دریافت از جمع‌آوری در '.$node->node_title, 'en' => 'Pickup reception at '.$node->node_title],
            'selection' => [
                'manifest_status' => 'IR', 'origin_node_id' => null,
                'destination_node_id' => (string) $node->node_id,
                'route_plan_id' => null, 'route_plan_leg_id' => null, 'transport_run_id' => null,
                'assigned_driver_id' => null, 'assigned_vehicle_id' => null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryAssignmentOption(object $node): array
    {
        return [
            'context_key' => 'DELIVERY_ASSIGNMENT',
            'operational_context_type' => 'DELIVERY_ASSIGNMENT',
            'manifest_status' => 'OD',
            'label' => ['fa' => 'تخصیص تحویل از '.$node->node_title, 'en' => 'Delivery assignment from '.$node->node_title],
            'selection' => [
                'manifest_status' => 'OD', 'origin_node_id' => (string) $node->node_id,
                'destination_node_id' => null, 'route_plan_id' => null,
                'route_plan_leg_id' => null, 'transport_run_id' => null,
                'assigned_driver_id' => null, 'assigned_vehicle_id' => null,
            ],
        ];
    }

    private function activeNode(string $hqId, string $nodeId): void
    {
        if (! DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->exists()) {
            throw $this->contextError('node_id', 'The operational node is not active in this HQ.');
        }
    }

    private function deliveryDriverExists(string $hqId, string $nodeId, string $driverId): bool
    {
        return DB::table('drivers as d')->where([
            'd.hq_id' => $hqId, 'd.driver_id' => $driverId, 'd.home_node_id' => $nodeId,
            'd.status' => 'ACTIVE', 'd.availability_status' => 'AVAILABLE',
        ])->whereExists(fn (Builder $capability) => $capability->selectRaw('1')->from('driver_capabilities as dc')
            ->whereColumn('dc.driver_id', 'd.driver_id')->whereColumn('dc.hq_id', 'd.hq_id')->where('dc.capability', 'DELIVERY'))
            ->exists();
    }

    /** @param array<string, mixed> $input @param list<string> $fields */
    private function assertNull(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (($input[$field] ?? null) !== null) {
                throw $this->contextError($field, 'This field is not applicable to the selected operational context.');
            }
        }
    }

    /** @param array<string, mixed> $input */
    private function assertMatches(array $input, string $field, string $authoritative): void
    {
        if (array_key_exists($field, $input) && $input[$field] !== null && (string) $input[$field] !== $authoritative) {
            throw $this->contextError($field, 'The selected value does not match the authoritative operational context.');
        }
    }

    private function contextError(string $field, string $message): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, $message, [$field => [$message]]);
    }

    /** @return array<string, mixed> */
    private function nodeResource(object $node): array
    {
        return [
            'node_id' => (string) $node->node_id,
            'node_code' => (string) $node->node_code,
            'node_title' => (string) $node->node_title,
            'node_type' => (string) $node->node_type,
            'status' => (string) $node->status,
        ];
    }

    /** @return array<string, mixed> */
    private function driverResource(object $driver): array
    {
        return [
            'driver_id' => (string) $driver->driver_id,
            'driver_code' => (string) $driver->driver_code,
            'display_name' => (string) $driver->display_name,
            'home_node_id' => (string) $driver->home_node_id,
            'status' => (string) $driver->status,
            'availability_status' => (string) $driver->availability_status,
        ];
    }

    /** @return array<string, mixed> */
    private function vehicleResource(object $vehicle): array
    {
        return [
            'vehicle_id' => (string) $vehicle->vehicle_id,
            'vehicle_code' => (string) $vehicle->vehicle_code,
            'registration_number' => (string) $vehicle->registration_number,
            'vehicle_type' => (string) $vehicle->vehicle_type,
            'home_node_id' => (string) $vehicle->home_node_id,
            'status' => (string) $vehicle->status,
            'availability_status' => (string) $vehicle->availability_status,
        ];
    }
}
