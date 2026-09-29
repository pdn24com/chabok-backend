<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Serialization;

use Modules\Manifest\Application\Dto\ManifestContextOptionDto;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class ManifestContextDocument
{
    public function optionResource(ManifestContextOptionDto $option): array
    {
        $selection = $option->selection;
        $request = ['expected_version' => 0, 'manifest_status' => $option->status, 'context_key' => $option->key];
        if ($selection->destinationNodeId !== null) {
            $request['target_node_id'] = $selection->destinationNodeId;
        }
        if ($selection->assignedDriverId !== null) {
            $request['assigned_driver_id'] = $selection->assignedDriverId;
        }
        if ($selection->assignedVehicleId !== null) {
            $request['assigned_vehicle_id'] = $selection->assignedVehicleId;
        }

        return [
            'context_key' => $option->key,
            'operational_context_type' => $option->type->value,
            'manifest_status' => $option->status,
            'label' => ['fa' => $option->label, 'en' => $option->label],
            'selection' => $request,
            'related_node' => $option->relatedNode === null ? null : $this->nodeResource($option->relatedNode),
            'target_node' => $option->targetNode === null ? null : $this->nodeResource($option->targetNode),
            'related_node_role' => $option->relatedNodeRole,
        ];
    }

    public function nodeResource(NodeRecord $r): array
    {
        return [
            'node_id' => (string) $r->node_id,
            'node_code' => (string) $r->node_code,
            'node_title' => (string) $r->node_title,
            'node_type' => (string) $r->node_type,
            'status' => (string) $r->status,
        ];
    }

    public function driverResource(DriverRecord $r): array
    {
        return [
            'driver_id' => (string) $r->driver_id,
            'driver_code' => (string) $r->driver_code,
            'display_name' => (string) $r->display_name,
            'home_node_id' => (string) $r->home_node_id,
            'status' => $r->status->value,
            'availability_status' => $r->availability_status->value,
        ];
    }

    public function vehicleResource(VehicleRecord $r): array
    {
        return [
            'vehicle_id' => (string) $r->vehicle_id,
            'vehicle_code' => (string) $r->vehicle_code,
            'registration_number' => (string) $r->registration_number,
            'vehicle_type' => $r->vehicle_type->value,
            'home_node_id' => (string) $r->home_node_id,
            'status' => $r->status->value,
            'availability_status' => $r->availability_status->value,
        ];
    }

    public function transitionContracts(): array
    {
        return array_map(fn (string $t): array => [
            'target_status' => $t,
            'allowed_source_statuses' => ManifestTransition::from($t)->sourceStatuses(),
            'operational_context_types' => array_column(ManifestTransition::from($t)->contextTypes(), 'value'),
            'current_node_requirement' => 'SERVER_VALIDATED',
            'origin_node_derivation' => 'SERVER_CONTEXT',
            'destination_node_derivation' => 'SERVER_CONTEXT',
            'route_plan_requirement' => in_array($t, ['ROU', 'OF', 'OS', 'CI', 'OD'], true) ? 'REQUIRED_OR_RESOLVED' : 'SOURCE_DEPENDENT',
            'route_leg_requirement' => in_array($t, ['ROU', 'OF', 'OS', 'CI'], true) ? 'REQUIRED' : 'SOURCE_DEPENDENT',
            'required_driver_capability' => ManifestTransition::from($t)->requiredDriverCapability()?->value,
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
        ], array_map(fn (ManifestTransition $transition) => $transition->value, ManifestTransition::cases()));
    }
}
