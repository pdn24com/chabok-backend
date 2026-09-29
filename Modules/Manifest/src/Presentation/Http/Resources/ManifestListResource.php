<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Manifest\Application\Serialization\ManifestTimestamp;
use Modules\Manifest\Domain\Enums\ManifestTransition;

final class ManifestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->manifest;
        $counts = $this->counts;
        $context = (new ManifestContextSummaryResource($this->context))->resolve($request);

        return [
            'manifest_id' => (string) $row->manifest_id,
            'manifest_number' => (string) $row->manifest_number,
            'node_id' => (string) $row->node_id,
            'manifest_status' => (string) $row->manifest_status,
            'state' => $row->state->value,
            'manifest_type' => ($row->manifest_type ?? ManifestTransition::from((string) $row->manifest_status)->manifestType())->value,
            'operational_context_type' => $row->operational_context_type->value,
            'context_key' => (string) $row->context_key,
            'origin_node_id' => $row->origin_node_id,
            'destination_node_id' => $row->destination_node_id,
            'route_plan_id' => $row->route_plan_id,
            'route_plan_leg_id' => $row->route_plan_leg_id,
            'assigned_driver_id' => $row->assigned_driver_id,
            'assigned_vehicle_id' => $row->assigned_vehicle_id,
            'version' => (int) $row->version,
            'total_count' => $counts->total(),
            'succeeded_count' => $counts->succeeded,
            'failed_count' => $counts->failed,
            'created_at' => ManifestTimestamp::format($row->created_at),
            'updated_at' => ManifestTimestamp::format($row->updated_at),
            'issuing_node' => $context['issuing_node'],
            'target_node' => $context['target_node'],
            'context' => $context,
        ];
    }
}
