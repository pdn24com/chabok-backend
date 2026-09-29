<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AvailableDriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $driver = $this->resource;
        $capabilities = $driver->capabilities->map(fn ($row) => $row->capability->value)->sort()->values()->all();

        return [
            'driver_id' => $driver->driver_id,
            'driver_code' => $driver->driver_code,
            'display_name' => $driver->display_name,
            'home_node_id' => $driver->home_node_id,
            'operational_type' => $driver->operational_type->value,
            'status' => $driver->status->value,
            'availability_status' => $driver->availability_status->value,
            'capabilities' => $capabilities,
            'version' => $driver->version,
        ];
    }
}
