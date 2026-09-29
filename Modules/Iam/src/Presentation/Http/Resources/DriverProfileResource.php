<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DriverProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['driver_id' => $this->id, 'driver_code' => $this->code, 'display_name' => $this->displayName,
            'home_node_id' => $this->homeNodeId, 'status' => $this->status];
    }
}
