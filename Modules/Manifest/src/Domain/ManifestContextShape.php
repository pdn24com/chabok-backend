<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain;

final readonly class ManifestContextShape
{
    public function baseOption(string $target, string $type, string $key, object $node, string $label): array
    {
        $s = $this->emptySelection();
        $s['origin_node_id'] = (string) $node->node_id;
        $s['destination_node_id'] = (string) $node->node_id;
        return $this->option($target, $type, $key, $label . ' · ' . $node->node_title, $s);
    }

    public function option(string $target, string $type, string $key, string $label, array $selection): array
    {
        $request = ['expected_version' => 0, 'manifest_status' => $target, 'context_key' => $key];
        if ($selection['destination_node_id'] !== null) {
            $request['target_node_id'] = $selection['destination_node_id'];
        }
        if ($selection['assigned_driver_id'] !== null) {
            $request['assigned_driver_id'] = $selection['assigned_driver_id'];
        }
        if ($selection['assigned_vehicle_id'] !== null) {
            $request['assigned_vehicle_id'] = $selection['assigned_vehicle_id'];
        }
        return [
            'context_key' => $key,
            'operational_context_type' => $type,
            'manifest_status' => $target,
            'label' => ['fa' => $label, 'en' => $label],
            'selection' => $request,
            'resolution' => $selection,
        ];
    }

    public function emptySelection(): array
    {
        return [
            'origin_node_id' => null,
            'destination_node_id' => null,
            'route_plan_id' => null,
            'route_definition_version_id' => null,
            'route_plan_leg_id' => null,
            'route_definition_version_leg_id' => null,
            'source_manifest_id' => null,
            'assigned_driver_id' => null,
            'assigned_vehicle_id' => null,
        ];
    }

    public function nodeResource(object $r): array
    {
        return [
            'node_id' => (string) $r->node_id,
            'node_code' => (string) $r->node_code,
            'node_title' => (string) $r->node_title,
            'node_type' => (string) $r->node_type,
            'status' => (string) $r->status,
        ];
    }

    public function driverResource(object $r): array
    {
        return [
            'driver_id' => (string) $r->driver_id,
            'driver_code' => (string) $r->driver_code,
            'display_name' => (string) $r->display_name,
            'home_node_id' => (string) $r->home_node_id,
            'status' => (string) $r->status,
            'availability_status' => (string) $r->availability_status,
        ];
    }

    public function vehicleResource(object $r): array
    {
        return [
            'vehicle_id' => (string) $r->vehicle_id,
            'vehicle_code' => (string) $r->vehicle_code,
            'registration_number' => (string) $r->registration_number,
            'vehicle_type' => (string) $r->vehicle_type,
            'home_node_id' => (string) $r->home_node_id,
            'status' => (string) $r->status,
            'availability_status' => (string) $r->availability_status,
        ];
    }

    public function manifestType(string $t): string
    {
        return match ($t) {
            'PD' => 'PICKUP_ASSIGNMENT',
            'PU' => 'PICKUP_COMPLETION',
            'NPU' => 'PICKUP_EXCEPTION',
            'IR' => 'INBOUND_RECEPTION',
            'ROU' => 'ROUTE_REGISTRATION',
            'OF' => 'OUTBOUND_TRANSFER',
            'OS' => 'LINEHAUL_DEPARTURE',
            'CI' => 'TRANSIT_UNLOAD',
            'OD' => 'DELIVERY_ASSIGNMENT',
            'OK' => 'DELIVERY_COMPLETION',
            'NOK' => 'DELIVERY_EXCEPTION',
        };
    }

    public function operationalContextType(string $t): string
    {
        return match ($t) {
            'PD' => 'PICKUP_ASSIGNMENT',
            'PU' => 'PICKUP_COMPLETION',
            'NPU' => 'PICKUP_EXCEPTION',
            'IR' => 'PICKUP_RECEPTION',
            'ROU' => 'ROUTE_REGISTRATION',
            'OF' => 'OUTBOUND_CONFIRMATION',
            'OS' => 'LINEHAUL_DEPARTURE',
            'CI' => 'TRANSIT_UNLOAD',
            'OD' => 'DELIVERY_ASSIGNMENT',
            'OK' => 'DELIVERY_COMPLETION',
            'NOK' => 'DELIVERY_EXCEPTION',
        };
    }

    public function transitionContracts(): array
    {
        $sources = [
            'PD' => ['CFM'],
            'PU' => ['PD'],
            'NPU' => ['PD'],
            'IR' => ['PU', 'OS'],
            'ROU' => ['IR'],
            'OF' => ['ROU', 'CI'],
            'OS' => ['OF'],
            'CI' => ['OS'],
            'OD' => ['IR'],
            'OK' => ['OD'],
            'NOK' => ['OD'],
        ];
        $types = [
            'PD' => ['PICKUP_ASSIGNMENT'],
            'PU' => ['PICKUP_COMPLETION'],
            'NPU' => ['PICKUP_EXCEPTION'],
            'IR' => ['PICKUP_RECEPTION', 'MOVEMENT_RECEPTION'],
            'ROU' => ['ROUTE_REGISTRATION'],
            'OF' => ['OUTBOUND_CONFIRMATION'],
            'OS' => ['LINEHAUL_DEPARTURE'],
            'CI' => ['TRANSIT_UNLOAD'],
            'OD' => ['DELIVERY_ASSIGNMENT'],
            'OK' => ['DELIVERY_COMPLETION'],
            'NOK' => ['DELIVERY_EXCEPTION'],
        ];
        return array_map(fn(string $t): array => [
            'target_status' => $t,
            'allowed_source_statuses' => $sources[$t],
            'operational_context_types' => $types[$t],
            'current_node_requirement' => 'SERVER_VALIDATED',
            'origin_node_derivation' => 'SERVER_CONTEXT',
            'destination_node_derivation' => 'SERVER_CONTEXT',
            'route_plan_requirement' => in_array($t, ['ROU', 'OF', 'OS', 'CI', 'OD'], true) ? 'REQUIRED_OR_RESOLVED' : 'SOURCE_DEPENDENT',
            'route_leg_requirement' => in_array($t, ['ROU', 'OF', 'OS', 'CI'], true) ? 'REQUIRED' : 'SOURCE_DEPENDENT',
            'required_driver_capability' => match ($t) {
                'PD' => 'PICKUP',
                'OS' => 'LINEHAUL',
                'OD' => 'DELIVERY',
                default => null,
            },
            'driver_requirement' => in_array($t, ['PD', 'OS', 'OD'], true) ? 'REQUIRED' : 'DERIVED',
            'vehicle_requirement' => $t === 'OS' ? 'REQUIRED_ACTIVE_AVAILABLE' : 'FORBIDDEN',
            'expected_custody_types' => match ($t) {
                'PU', 'NPU' => ['PICKUP_DRIVER'],
                'IR' => ['PICKUP_DRIVER', 'LINEHAUL_DRIVER'],
                'CI' => ['LINEHAUL_DRIVER'],
                'OK', 'NOK' => ['DELIVERY_DRIVER'],
                default => ['NODE'],
            },
            'exception_review_required' => in_array($t, ['NPU', 'NOK'], true),
            'immutable_evidence' => [
                'AUTHENTICATED_ACTOR',
                'SERVER_TIMESTAMP',
                'MANIFEST',
                'MANIFEST_PARCELS',
                'STATUS_HISTORY',
                'CUSTODY_HISTORY',
                'AUDIT',
                'OUTBOX',
            ],
        ], array_keys($sources));
    }
}
