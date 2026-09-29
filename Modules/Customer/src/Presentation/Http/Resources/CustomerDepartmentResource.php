<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;

/**
 * One node of the chart with the posts it holds, but without its children; the tree resource nests those.
 *
 * @mixin CustomerDepartmentRecord
 */
final class CustomerDepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'department_id' => $this->customer_department_id,
            'parent_department_id' => $this->parent_department_id === null ? null : (string) $this->parent_department_id,
            'title' => $this->title,
            'cost_center_code' => $this->cost_center_code,
            'positions' => CustomerPositionResource::collection($this->positions)->resolve($request),
        ];
    }
}
