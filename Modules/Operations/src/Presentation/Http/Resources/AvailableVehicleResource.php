<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AvailableVehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vehicle = $this->resource;

        return [
            'vehicle_id' => $vehicle->vehicle_id,
            'vehicle_code' => $vehicle->vehicle_code,
            'registration_number' => $vehicle->registration_number,
            'vehicle_type' => $vehicle->vehicle_type->value,
            'home_node_id' => $vehicle->home_node_id,
            'status' => $vehicle->status->value,
            'availability_status' => $vehicle->availability_status->value,
            'version' => $vehicle->version,
        ];
    }
}
