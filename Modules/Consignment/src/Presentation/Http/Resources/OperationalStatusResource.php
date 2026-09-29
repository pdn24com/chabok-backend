<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OperationalStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->resource->status->attributesToArray(), 'can_manage' => $this->resource->canManage];
    }
}
