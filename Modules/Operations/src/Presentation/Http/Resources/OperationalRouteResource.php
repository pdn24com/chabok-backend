<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OperationalRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $route = $this->resource;
        $legs = [];
        foreach ($route->legs as $leg) {
            $legs[] = [
                'route_definition_leg_id' => $leg->route_definition_leg_id,
                'leg_order' => $leg->leg_order,
                'origin_node' => [
                    'node_id' => $leg->originNode->node_id,
                    'node_code' => $leg->originNode->node_code,
                    'node_title' => $leg->originNode->node_title,
                ],
                'destination_node' => [
                    'node_id' => $leg->destinationNode->node_id,
                    'node_code' => $leg->destinationNode->node_code,
                    'node_title' => $leg->destinationNode->node_title,
                ],
            ];
        }

        return [
            'route_definition_id' => $route->route_definition_id,
            'route_code' => $route->route_code,
            'route_title' => $route->route_title,
            'status' => $route->status,
            'version' => $route->version,
            'legs' => $legs,
        ];
    }
}
