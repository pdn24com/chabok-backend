<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Manifest\Application\Serialization\ManifestContextDocument;

final class ManifestContextSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $document = new ManifestContextDocument;
        $context = $this->resource;

        return [
            'operational_context_type' => $context->type->value,
            'issuing_node' => $context->issuingNode === null ? null : $document->nodeResource($context->issuingNode),
            'target_node' => $context->targetNode === null ? null : $document->nodeResource($context->targetNode),
            'current_node' => $context->currentNode === null ? null : $document->nodeResource($context->currentNode),
            'related_node' => $context->relatedNode === null ? null : $document->nodeResource($context->relatedNode),
            'origin_node' => $context->originNode === null ? null : $document->nodeResource($context->originNode),
            'destination_node' => $context->destinationNode === null ? null : $document->nodeResource($context->destinationNode),
            'related_node_role' => $context->relatedNodeRole,
            'route_plan' => $context->routePlan === null ? null : [
                'route_plan_id' => $context->routePlan->route_plan_id, 'consignment_id' => $context->routePlan->consignment_id,
                'consignment_number' => $context->routePlan->consignment->consignment_number,
                'route_definition_id' => $context->routePlan->route_definition_id, 'route_code' => $context->routePlan->definition->route_code,
                'route_title' => $context->routePlan->definition->route_title, 'status' => $context->routePlan->status, 'version' => (int) $context->routePlan->version,
            ],
            'route_leg' => $context->routeLeg === null ? null : [
                'route_plan_leg_id' => $context->routeLeg->route_plan_leg_id, 'leg_order' => (int) $context->routeLeg->leg_order,
                'status' => $context->routeLeg->status, 'origin_node_id' => $context->routeLeg->origin_node_id, 'destination_node_id' => $context->routeLeg->destination_node_id,
            ],
            'driver' => $context->driver === null ? null : $document->driverResource($context->driver),
            'vehicle' => $context->vehicle === null ? null : $document->vehicleResource($context->vehicle),
        ];
    }
}
