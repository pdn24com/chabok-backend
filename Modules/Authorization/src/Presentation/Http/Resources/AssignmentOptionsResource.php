<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsResult;

/** @mixin GetAssignmentOptionsResult */
final class AssignmentOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $areas = [];
        foreach ($this->areas as $option) {
            $areas[] = [
                'area_id' => $option->area->area_id,
                'area_title' => $option->area->area_title,
                'path' => $option->path,
                'can_include_descendants' => $option->canIncludeDescendants,
            ];
        }
        $nodes = [];
        foreach ($this->nodes as $node) {
            $nodes[] = [
                'node_id' => $node->node_id,
                'node_title' => $node->node_title,
                'node_code' => $node->node_code,
                'node_type' => $node->node_type,
                'area_id' => $node->area_id,
            ];
        }

        return ['tenant_allowed' => $this->tenantAllowed, 'areas' => $areas, 'nodes' => $nodes];
    }
}
