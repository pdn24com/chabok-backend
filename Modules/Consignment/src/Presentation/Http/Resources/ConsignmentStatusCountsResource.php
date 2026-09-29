<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConsignmentStatusCountsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource->total, 'new_routed' => $this->resource->newRouted,
            'unassigned' => $this->resource->unassigned, 'assigned' => $this->resource->assigned,
            'in_operation' => $this->resource->inOperation, 'exception' => $this->resource->exception,
            'completed' => $this->resource->completed, 'cancelled' => $this->resource->cancelled,
        ];
    }
}
