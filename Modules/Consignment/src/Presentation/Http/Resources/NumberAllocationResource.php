<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberAllocationRecord;

final class NumberAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var NumberAllocationRecord $row */
        $row = $this->resource;

        return [
            'allocation_id' => (string) $row->allocation_id,
            'range_id' => (string) $row->range_id,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment_number,
            'allocated_by' => (string) $row->allocated_by,
            'allocated_at' => (string) $row->allocated_at,
            'correlation_id' => (string) $row->correlation_id,
        ];
    }
}
