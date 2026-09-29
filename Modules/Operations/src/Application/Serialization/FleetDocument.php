<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Serialization;

use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final class FleetDocument
{
    public static function driver(DriverRecord $row): array
    {
        return [
            'driver_id' => (string) $row->driver_id,
            'driver_code' => (string) $row->driver_code,
            'display_name' => (string) $row->display_name,
            'user_id' => $row->user_id === null ? null : (string) $row->user_id,
            'home_node_id' => (string) $row->home_node_id,
            'mobile' => $row->mobile === null ? null : (string) $row->mobile,
            'capabilities' => $row->capabilities->sortBy(fn ($capability) => $capability->capability->displayOrder())->map(fn ($capability) => $capability->capability->value)->values()->all(),
            'status' => $row->status->value,
            'availability_status' => $row->availability_status->value,
            'version' => (int) $row->version,
        ];
    }

    public static function vehicle(VehicleRecord $row): array
    {
        return [
            'vehicle_id' => (string) $row->vehicle_id,
            'vehicle_code' => (string) $row->vehicle_code,
            'plate_number' => (string) ($row->plate_number ?? $row->registration_number),
            'vehicle_type' => $row->vehicle_type->value,
            'home_node_id' => (string) $row->home_node_id,
            'capacity_weight_grams' => $row->capacity_weight_grams === null ? null : (int) $row->capacity_weight_grams,
            'capacity_volume_cm3' => $row->capacity_volume_cm3 === null ? null : (int) $row->capacity_volume_cm3,
            'status' => $row->status->value,
            'availability_status' => $row->availability_status->value,
            'version' => (int) $row->version,
        ];
    }
}
