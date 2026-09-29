<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestContextAccessInterface;
use Modules\Manifest\Application\Contracts\ManifestContextOptionsInterface;
use Modules\Manifest\Application\Contracts\ManifestContextReferencesInterface;
use Modules\Manifest\Application\Dto\ManifestContextOptionDto;
use Modules\Manifest\Application\Dto\ManifestSelectionDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class ManifestContextOptions implements ManifestContextOptionsInterface
{
    public function __construct(
        private ManifestContextReferencesInterface $manifestContextReferences,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestRouteAccessInterface $manifestRouteAccess,
        private ManifestContextAccessInterface $manifestContextAccess,
        private ManifestDirectoryReaderInterface $manifestDirectoryReader,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function operationalOptions(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        NodeRecord $node,
    ): array {
        $options = [
            $this->baseOption('PD', 'PICKUP_ASSIGNMENT', "PD:{$nodeId}", $node, 'Pickup assignment'),
            $this->baseOption('IR', 'PICKUP_RECEPTION', "IR:PICKUP:{$nodeId}", $node, 'Pickup reception'),
            $this->baseOption('ROU', 'ROUTE_REGISTRATION', "ROU:{$nodeId}", $node, 'Route registration'),
            $this->baseOption('OD', 'DELIVERY_ASSIGNMENT', "OD:{$nodeId}", $node, 'Delivery assignment'),
            ...$this->pickupOptions((string) $actor->hqId, $nodeId),
            ...$this->routeLegOptions((string) $actor->hqId, $nodeId),
            ...$this->movementReceptionOptions((string) $actor->hqId, $nodeId),
            ...$this->deliveryOptions((string) $actor->hqId, $nodeId),
            ...$this->draftOptions($actor, $nodeId),
        ];

        $nodeIds = [$nodeId];
        foreach ($options as $option) {
            $nodeIds[] = $option->selection->originNodeId;
            $nodeIds[] = $option->selection->destinationNodeId;
        }
        $nodes = $this->manifestDirectoryReader->nodesByIds(array_values(array_unique(array_filter($nodeIds))), (string) $actor->hqId)->keyBy('node_id');
        foreach ($options as $option) {
            $selection = $option->selection;
            if (in_array($option->status, ['IR', 'CI'], true)) {
                $relatedNodeId = $selection->originNodeId;
                $option->relatedNodeRole = 'SOURCE';
            } elseif (in_array($option->status, ['OF', 'OS'], true)) {
                $relatedNodeId = $selection->destinationNodeId;
                $option->relatedNodeRole = 'DESTINATION';
            } else {
                $relatedNodeId = $selection->destinationNodeId ?? $selection->originNodeId ?? $nodeId;
            }
            $option->relatedNode = $nodes->get($relatedNodeId);
            $option->targetNode = $nodes->get($selection->destinationNodeId ?? $nodeId);
        }

        return $options;
    }

    public function pickupOptions(string $hq, string $node): array
    {
        return array_merge([], ...array_map(function (PickupTaskRecord $r) use ($node): array {
            $s = new ManifestSelectionDto;
            $s->originNodeId = $node;
            $s->destinationNodeId = $node;
            $s->assignedDriverId = (string) $r->assigned_driver_id;

            return [
                new ManifestContextOptionDto('PU', 'PICKUP_COMPLETION', 'PICKUP:'.$r->pickup_task_id.':PU', "{$r->consignment->consignment_number} · pickup completion", $s),
                new ManifestContextOptionDto('NPU', 'PICKUP_EXCEPTION', 'PICKUP:'.$r->pickup_task_id.':NPU', "{$r->consignment->consignment_number} · pickup exception", $s),
            ];
        }, $this->manifestTaskAccess->pickupContexts($hq, $node)->all()));
    }

    public function routeLegOptions(string $hq, string $node): array
    {
        $rows = $this->manifestRouteAccess->outboundLegContexts($hq, $node);
        $out = [];
        foreach ($rows as $r) {
            $s = new ManifestSelectionDto;
            $s->originNodeId = $node;
            $s->destinationNodeId = (string) $r->destination_node_id;
            $s->routePlanId = (string) $r->route_plan_id;
            $s->routePlanLegId = (string) $r->route_plan_leg_id;
            $s->routeDefinitionVersionId = (string) $r->plan->route_definition_version_id;
            $s->routeDefinitionVersionLegId = (string) $r->source_route_definition_version_leg_id;
            $out[] = new ManifestContextOptionDto('OF', 'OUTBOUND_CONFIRMATION', 'OF:'.$r->route_plan_leg_id, "{$r->plan->consignment->consignment_number} · outbound leg {$r->leg_order}", $s);
        }
        $outboundManifests = $this->manifestRepository->closedOutboundTransfers($hq, $node);
        foreach ($outboundManifests as $r) {
            $s = new ManifestSelectionDto;
            $s->originNodeId = (string) $r->origin_node_id;
            $s->destinationNodeId = (string) $r->destination_node_id;
            $s->routeDefinitionVersionLegId = (string) $r->route_definition_version_leg_id;
            $s->sourceManifestId = (string) $r->manifest_id;
            $out[] = new ManifestContextOptionDto('OS', 'LINEHAUL_DEPARTURE', 'OS:OF:'.$r->manifest_id, 'Linehaul departure · '.$r->manifest_number, $s);
        }

        return $out;
    }

    public function movementReceptionOptions(string $hq, string $node): array
    {
        $out = [];
        $manifests = $this->manifestRepository->inboundLinehaulArrivals($hq, $node);
        foreach ($manifests as $r) {
            $s = new ManifestSelectionDto;
            $s->originNodeId = (string) $r->origin_node_id;
            $s->destinationNodeId = (string) $r->destination_node_id;
            $s->sourceManifestId = (string) $r->manifest_id;
            $s->assignedDriverId = (string) $r->assigned_driver_id;
            $s->assignedVehicleId = (string) $r->assigned_vehicle_id;
            $hasTransit = $hasFinal = false;
            foreach ($r->parcels as $row) {
                $leg = $row->routeLeg;
                if ($leg === null) {
                    continue;
                }
                $next = $leg->plan?->legs->first(fn ($next) => $next->leg_order === $leg->leg_order + 1);
                $hasTransit = $hasTransit || $next !== null && $next->origin_node_id === $node;
                $hasFinal = $hasFinal || $next === null;
            }
            if ($hasTransit) {
                $out[] = new ManifestContextOptionDto('CI', ManifestContextType::TransitUnload, 'CI:OS:'.$r->manifest_id, 'Transit unload · '.$r->manifest_number, $s);
            }
            if ($hasFinal) {
                $out[] = new ManifestContextOptionDto('IR', ManifestContextType::MovementReception, 'IR:OS:'.$r->manifest_id, 'Final reception · '.$r->manifest_number, $s);
            }
        }

        return $out;
    }

    public function deliveryOptions(string $hq, string $node): array
    {
        return array_merge([], ...array_map(function (DeliveryTaskRecord $r) use ($node): array {
            $s = new ManifestSelectionDto;
            $s->originNodeId = $node;
            $s->destinationNodeId = $node;
            $s->assignedDriverId = (string) $r->assigned_driver_id;

            return [
                new ManifestContextOptionDto('OK', 'DELIVERY_COMPLETION', 'DELIVERY:'.$r->delivery_task_id.':OK', "{$r->consignment->consignment_number} · delivery completion", $s),
                new ManifestContextOptionDto('NOK', 'DELIVERY_EXCEPTION', 'DELIVERY:'.$r->delivery_task_id.':NOK', "{$r->consignment->consignment_number} · delivery exception", $s),
            ];
        }, $this->manifestTaskAccess->deliveryContexts($hq, $node)->all()));
    }

    public function draftOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $options = [];
        foreach ($this->manifestContextReferences->targetNodes((string) $actor->hqId, $this->manifestContextAccess->accessibleNodeIds($actor)) as $targetNode) {
            foreach (['PD', 'PU', 'NPU', 'IR', 'ROU', 'OF', 'OS', 'CI', 'OD', 'OK', 'NOK'] as $target) {
                $selection = new ManifestSelectionDto;
                $selection->originNodeId = $nodeId;
                $selection->destinationNodeId = $targetNode['node_id'];
                $options[] = new ManifestContextOptionDto($target, ManifestTransition::from($target)->defaultContextType(), "DRAFT:{$target}:{$targetNode['node_id']}", "{$target} · {$targetNode['node_title']}", $selection);
            }
        }

        return $options;
    }

    private function baseOption(string $target, string $type, string $key, NodeRecord $node, string $label): ManifestContextOptionDto
    {
        return new ManifestContextOptionDto($target, $type, $key, $label.' · '.$node->node_title,
            new ManifestSelectionDto(originNodeId: $node->node_id, destinationNodeId: $node->node_id));
    }
}
