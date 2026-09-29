<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final class ConsignmentFilterOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $choice = fn (DriverRecord $driver): array => ['value' => $driver->driver_id, 'label' => $driver->display_name];

        return [
            'pickup_agents' => $this->resource->pickupDrivers->map($choice)->all(),
            'delivery_agents' => $this->resource->deliveryDrivers->map($choice)->all(),
        ];
    }
}
