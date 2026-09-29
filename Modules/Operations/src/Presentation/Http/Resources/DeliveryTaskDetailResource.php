<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Operations\Infrastructure\Persistence\Models\LastMileResolutionRecord;

final class DeliveryTaskDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            ...(new DeliveryTaskResource($row))->resolve($request),
            'node' => ['node_id' => $row->node_id, 'node_code' => $row->node->node_code, 'node_title' => $row->node->node_title],
            'driver' => $row->assigned_driver_id === null ? null : [
                'driver_id' => $row->assigned_driver_id, 'driver_code' => (string) $row->assignedDriver?->driver_code, 'display_name' => (string) $row->assignedDriver?->display_name,
            ],
            'parcels' => $row->parcels->map(fn ($parcel): array => [
                'parcel_id' => (string) $parcel->parcel_id,
                'parcel_number' => (string) $parcel->parcel_number,
                'current_status' => (string) $parcel->current_status,
                'current_node_id' => $parcel->current_node_id,
                'current_custody_type' => (string) $parcel->current_custody_type,
                'current_custodian_id' => $parcel->current_custodian_id,
            ])->all(),
            'history' => $row->history->map(fn ($event): array => [
                'event_type' => (string) $event->event_type,
                'from_status' => $event->from_status,
                'to_status' => (string) $event->to_status,
                'attempt_number' => (int) $event->attempt_number,
                'assigned_driver_id' => $event->assigned_driver_id,
                'reason_code' => $event->reason_code,
                'safe_note' => $event->safe_note,
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at,
            ])->all(),
            'last_mile_resolution' => $row->resolution === null ? null : $this->resolution($row->resolution),
            'route_progress' => $row->routeLegs->map(fn ($leg): array => [
                'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'status' => (string) $leg->status,
                'origin_node' => [
                    'node_id' => (string) $leg->origin_node_id,
                    'node_code' => (string) $leg->originNode->node_code,
                    'node_title' => (string) $leg->originNode->node_title,
                ],
                'destination_node' => [
                    'node_id' => (string) $leg->destination_node_id,
                    'node_code' => (string) $leg->destinationNode->node_code,
                    'node_title' => (string) $leg->destinationNode->node_title,
                ],
                'routed_at' => $leg->routed_at,
                'received_at' => $leg->received_at,
            ])->all(),
            'permitted_actions' => match ($row->status->value) {
                'PENDING' => ['ASSIGN'], 'ASSIGNED' => ['REASSIGN'], 'IN_PROGRESS' => ['COMPLETE', 'FAIL'], 'FAILED' => ['RETRY'], default => [],
            },
        ];
    }

    private function resolution(LastMileResolutionRecord $row): array
    {
        return [
            'last_mile_resolution_id' => (string) $row->last_mile_resolution_id,
            'destination_gateway_node_id' => (string) $row->destination_gateway_node_id,
            'last_mile_node_id' => (string) $row->last_mile_node_id,
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'coverage_policy_version_id' => (string) $row->coverage_policy_version_id,
            'coverage_rule_id' => (string) $row->coverage_rule_id,
            'route_definition_version_id' => $row->route_definition_version_id,
            'resolution_input' => $row->resolution_input,
            'resolved_at' => $row->resolved_at,
        ];
    }
}
