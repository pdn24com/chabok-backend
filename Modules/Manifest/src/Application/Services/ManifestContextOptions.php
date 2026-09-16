<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestContextOptions
{
    public function __construct(
        private \Modules\Manifest\Domain\ManifestContextShape $manifestContextShape,
        private \Modules\Manifest\Application\Services\ManifestContextReferences $manifestContextReferences,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
        private \Modules\Operations\Application\Contracts\ManifestRouteAccess $routeState,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Manifest\Application\Services\ManifestContextAccess $manifestContextAccess,
    )
    {
    }

    public function operationalOptions(AuthenticatedPrincipal $actor, string $nodeId, object $node): array
    {
        $options = [
            $this->manifestContextShape->baseOption('PD', 'PICKUP_ASSIGNMENT', "PD:{$nodeId}", $node, 'Pickup assignment'),
            $this->manifestContextShape->baseOption('IR', 'PICKUP_RECEPTION', "IR:PICKUP:{$nodeId}", $node, 'Pickup reception'),
            $this->manifestContextShape->baseOption('ROU', 'ROUTE_REGISTRATION', "ROU:{$nodeId}", $node, 'Route registration'),
            $this->manifestContextShape->baseOption('OD', 'DELIVERY_ASSIGNMENT', "OD:{$nodeId}", $node, 'Delivery assignment'),
            ...$this->pickupOptions((string) $actor->hqId, $nodeId),
            ...$this->routeLegOptions((string) $actor->hqId, $nodeId),
            ...$this->movementReceptionOptions((string) $actor->hqId, $nodeId),
            ...$this->deliveryOptions((string) $actor->hqId, $nodeId),
            ...$this->draftOptions($actor, $nodeId),
        ];
        return array_map(function (array $option) use ($actor, $nodeId): array {
            $target = (string) $option['manifest_status'];
            $relatedNodeId = in_array($target, ['IR', 'CI'], true) ? $option['resolution']['origin_node_id'] : (in_array($target, ['OF', 'OS'], true) ? $option['resolution']['destination_node_id'] : $option['resolution']['destination_node_id'] ?? $option['resolution']['origin_node_id'] ?? $nodeId);
            $option['related_node'] = $this->manifestContextReferences->nullableNode((string) $actor->hqId, $relatedNodeId);
            $option['target_node'] = $this->manifestContextReferences->nullableNode((string) $actor->hqId, $option['resolution']['destination_node_id'] ?? $nodeId);
            $option['related_node_role'] = in_array($target, ['IR', 'CI'], true) ? 'SOURCE' : (in_array($target, ['OF', 'OS'], true) ? 'DESTINATION' : 'COUNTERPARTY');
            return $option;
        }, $options);
    }

    public function pickupOptions(string $hq, string $node): array
    {
        return array_merge([], ...array_map(function (object $r) use ($node): array {
            $s = $this->manifestContextShape->emptySelection();
            $s['origin_node_id'] = $node;
            $s['destination_node_id'] = $node;
            $s['assigned_driver_id'] = (string) $r->assigned_driver_id;
            return [
                $this->manifestContextShape->option('PU', 'PICKUP_COMPLETION', 'PICKUP:' . $r->pickup_task_id . ':PU', "{$r->consignment_number} · pickup completion", $s),
                $this->manifestContextShape->option('NPU', 'PICKUP_EXCEPTION', 'PICKUP:' . $r->pickup_task_id . ':NPU', "{$r->consignment_number} · pickup exception", $s),
            ];
        }, $this->taskState->pickupContexts($hq, $node)));
    }

    public function routeLegOptions(string $hq, string $node): array
    {
        $rows = $this->routeState->outboundLegContexts($hq, $node);
        $out = [];
        foreach ($rows as $r) {
            $s = $this->manifestContextShape->emptySelection();
            $s['origin_node_id'] = $node;
            $s['destination_node_id'] = (string) $r->destination_node_id;
            $s['route_plan_id'] = (string) $r->route_plan_id;
            $s['route_plan_leg_id'] = (string) $r->route_plan_leg_id;
            $s['route_definition_version_id'] = (string) $r->route_definition_version_id;
            $s['route_definition_version_leg_id'] = (string) $r->source_route_definition_version_leg_id;
            $out[] = $this->manifestContextShape->option('OF', 'OUTBOUND_CONFIRMATION', 'OF:' . $r->route_plan_leg_id, "{$r->consignment_number} · outbound leg {$r->leg_order}", $s);
        }
        $outboundManifests = $this->workflow->closedOutboundManifests($hq, $node);
        foreach ($outboundManifests as $r) {
            $s = $this->manifestContextShape->emptySelection();
            $s['origin_node_id'] = (string) $r->origin_node_id;
            $s['destination_node_id'] = (string) $r->destination_node_id;
            $s['route_definition_version_leg_id'] = (string) $r->route_definition_version_leg_id;
            $s['source_manifest_id'] = (string) $r->manifest_id;
            $out[] = $this->manifestContextShape->option('OS', 'LINEHAUL_DEPARTURE', 'OS:OF:' . $r->manifest_id, 'Linehaul departure · ' . $r->manifest_number, $s);
        }
        return $out;
    }

    public function movementReceptionOptions(string $hq, string $node): array
    {
        $out = [];
        $manifests = $this->workflow->receptionManifests($hq, $node);
        foreach ($manifests as $r) {
            $s = $this->manifestContextShape->emptySelection();
            $s['origin_node_id'] = (string) $r->origin_node_id;
            $s['destination_node_id'] = (string) $r->destination_node_id;
            $s['source_manifest_id'] = (string) $r->manifest_id;
            $s['assigned_driver_id'] = (string) $r->assigned_driver_id;
            $s['assigned_vehicle_id'] = (string) $r->assigned_vehicle_id;
            $hasTransit = $this->workflow->hasTransitReception($r->manifest_id, $node);
            $hasFinal = $this->workflow->hasFinalReception($r->manifest_id);
            if ($hasTransit) {
                $out[] = $this->manifestContextShape->option('CI', 'TRANSIT_UNLOAD', 'CI:OS:' . $r->manifest_id, 'Transit unload · ' . $r->manifest_number, $s);
            }
            if ($hasFinal) {
                $out[] = $this->manifestContextShape->option('IR', 'MOVEMENT_RECEPTION', 'IR:OS:' . $r->manifest_id, 'Final reception · ' . $r->manifest_number, $s);
            }
        }
        return $out;
    }

    public function deliveryOptions(string $hq, string $node): array
    {
        return array_merge([], ...array_map(function (object $r) use ($node): array {
            $s = $this->manifestContextShape->emptySelection();
            $s['origin_node_id'] = $node;
            $s['destination_node_id'] = $node;
            $s['assigned_driver_id'] = (string) $r->assigned_driver_id;
            return [
                $this->manifestContextShape->option('OK', 'DELIVERY_COMPLETION', 'DELIVERY:' . $r->delivery_task_id . ':OK', "{$r->consignment_number} · delivery completion", $s),
                $this->manifestContextShape->option('NOK', 'DELIVERY_EXCEPTION', 'DELIVERY:' . $r->delivery_task_id . ':NOK', "{$r->consignment_number} · delivery exception", $s),
            ];
        }, $this->taskState->deliveryContexts($hq, $node)));
    }

    public function draftOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $options = [];
        foreach ($this->manifestContextReferences->targetNodes((string) $actor->hqId, $this->manifestContextAccess->accessibleNodeIds($actor)) as $targetNode) {
            foreach (['PD', 'PU', 'NPU', 'IR', 'ROU', 'OF', 'OS', 'CI', 'OD', 'OK', 'NOK'] as $target) {
                $selection = $this->manifestContextShape->emptySelection();
                $selection['origin_node_id'] = $nodeId;
                $selection['destination_node_id'] = $targetNode['node_id'];
                $options[] = $this->manifestContextShape->option($target, $this->manifestContextShape->operationalContextType($target), "DRAFT:{$target}:{$targetNode['node_id']}", "{$target} · {$targetNode['node_title']}", $selection);
            }
        }
        return $options;
    }
}
