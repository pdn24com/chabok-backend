<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

final readonly class ManifestContextProjection
{
    public function __construct(
        private \Modules\Manifest\Domain\ManifestContextShape $manifestContextShape,
        private \Modules\Operations\Application\Contracts\ManifestDirectoryReader $directory,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
        private \Modules\Operations\Application\Contracts\ManifestRouteAccess $routeState,
        private \Modules\Manifest\Application\Services\ManifestContextReferences $manifestContextReferences,
    )
    {
    }

    public function summaries(string $hq, array $manifests): array
    {
        if ($manifests === []) {
            return [];
        }
        $ids = static fn(array $fields): array => array_values(array_unique(array_filter(array_merge([], ...array_map(static fn($row): array => array_map(static fn($field) => $row->{$field}, $fields), $manifests))), SORT_REGULAR));
        $references = [];
        $references['nodes'] = array_replace([], ...array_map(fn($row) => [$row->node_id => $this->manifestContextShape->nodeResource($row)], $this->directory->nodesByIds($ids(['node_id', 'origin_node_id', 'destination_node_id']), $hq)));
        $references['drivers'] = array_replace([], ...array_map(fn($row) => [$row->driver_id => $this->manifestContextShape->driverResource($row)], $this->taskState->driversByIds($ids(['assigned_driver_id']), $hq)));
        $references['vehicles'] = array_replace([], ...array_map(fn($row) => [$row->vehicle_id => $this->manifestContextShape->vehicleResource($row)], $this->taskState->vehiclesByIds($ids(['assigned_vehicle_id']), $hq)));
        $references['plans'] = array_replace([], ...array_map(fn($r) => [$r->route_plan_id => [
            'route_plan_id' => (string) $r->route_plan_id,
            'consignment_id' => (string) $r->consignment_id,
            'consignment_number' => (string) $r->consignment_number,
            'route_definition_id' => (string) $r->route_definition_id,
            'route_code' => (string) $r->route_code,
            'route_title' => (string) $r->route_title,
            'status' => (string) $r->status,
            'version' => (int) $r->version,
        ]], $this->routeState->plansByIds($ids(['route_plan_id']), $hq)));
        $references['legs'] = array_replace([], ...array_map(fn($r) => [$r->route_plan_leg_id => [
            'route_plan_leg_id' => (string) $r->route_plan_leg_id,
            'leg_order' => (int) $r->leg_order,
            'status' => (string) $r->status,
            'origin_node_id' => (string) $r->origin_node_id,
            'destination_node_id' => (string) $r->destination_node_id,
        ]], $this->routeState->legsByIds($ids(['route_plan_leg_id']), $hq)));
        return array_replace([], ...array_map(fn($row) => [$row->manifest_id => $this->summary($row, $references)], $manifests));
    }

    public function summary(object $manifest, ?array $references = null): array
    {
        $hq = (string) $manifest->hq_id;
        $target = (string) $manifest->manifest_status;
        $relatedNodeId = in_array($target, ['IR', 'CI'], true) ? $manifest->origin_node_id : (in_array($target, ['OF', 'OS'], true) ? $manifest->destination_node_id : $manifest->destination_node_id ?? $manifest->origin_node_id);
        return [
            'operational_context_type' => (string) $manifest->operational_context_type,
            'issuing_node' => $references !== null ? $references['nodes'][$manifest->node_id] ?? null : $this->manifestContextReferences->nullableNode($hq, $manifest->node_id),
            'target_node' => $references !== null ? $references['nodes'][$manifest->destination_node_id] ?? null : $this->manifestContextReferences->nullableNode($hq, $manifest->destination_node_id),
            'current_node' => $references !== null ? $references['nodes'][$manifest->node_id] ?? null : $this->manifestContextReferences->nullableNode($hq, $manifest->node_id),
            'related_node' => $references !== null ? $references['nodes'][$relatedNodeId] ?? null : $this->manifestContextReferences->nullableNode($hq, $relatedNodeId),
            'related_node_role' => in_array($target, ['IR', 'CI'], true) ? 'SOURCE' : (in_array($target, ['OF', 'OS'], true) ? 'DESTINATION' : 'COUNTERPARTY'),
            'origin_node' => $references !== null ? $references['nodes'][$manifest->origin_node_id] ?? null : $this->manifestContextReferences->nullableNode($hq, $manifest->origin_node_id),
            'destination_node' => $references !== null ? $references['nodes'][$manifest->destination_node_id] ?? null : $this->manifestContextReferences->nullableNode($hq, $manifest->destination_node_id),
            'route_plan' => $references !== null ? $references['plans'][$manifest->route_plan_id] ?? null : $this->manifestContextReferences->routePlan($hq, $manifest->route_plan_id),
            'route_leg' => $references !== null ? $references['legs'][$manifest->route_plan_leg_id] ?? null : $this->manifestContextReferences->routeLeg($hq, $manifest->route_plan_leg_id),
            'driver' => $references !== null ? $references['drivers'][$manifest->assigned_driver_id] ?? null : $this->manifestContextReferences->driver($hq, $manifest->assigned_driver_id),
            'vehicle' => $references !== null ? $references['vehicles'][$manifest->assigned_vehicle_id] ?? null : $this->manifestContextReferences->vehicle($hq, $manifest->assigned_vehicle_id),
        ];
    }
}
