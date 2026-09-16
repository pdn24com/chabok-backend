<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class FleetProjection
{
    public function __construct(private \Modules\Operations\Application\Repositories\FleetRepository $fleet)
    {
    }

    public function driver(array $row, array $capabilities): array
    {
        return [
            'driver_id' => (string) $row['driver_id'],
            'driver_code' => (string) $row['driver_code'],
            'display_name' => (string) $row['display_name'],
            'user_id' => $row['user_id'] === null ? null : (string) $row['user_id'],
            'home_node_id' => (string) $row['home_node_id'],
            'mobile' => $row['mobile'] === null ? null : (string) $row['mobile'],
            'capabilities' => array_values($capabilities),
            'status' => (string) $row['status'],
            'availability_status' => (string) $row['availability_status'],
            'version' => (int) $row['version'],
        ];
    }

    public function vehicle(array $row): array
    {
        return [
            'vehicle_id' => (string) $row['vehicle_id'],
            'vehicle_code' => (string) $row['vehicle_code'],
            'plate_number' => (string) ($row['plate_number'] ?? $row['registration_number']),
            'vehicle_type' => (string) $row['vehicle_type'],
            'home_node_id' => (string) $row['home_node_id'],
            'capacity_weight_grams' => $row['capacity_weight_grams'] === null ? null : (int) $row['capacity_weight_grams'],
            'capacity_volume_cm3' => $row['capacity_volume_cm3'] === null ? null : (int) $row['capacity_volume_cm3'],
            'status' => (string) $row['status'],
            'availability_status' => (string) $row['availability_status'],
            'version' => (int) $row['version'],
        ];
    }

    public function driverDetailUnchecked(AuthenticatedPrincipal $actor, string $driverId): array
    {
        $row = (array) $this->fleet->findDriver($actor->hqId, $driverId);
        $capabilities = $this->fleet->driverCapabilities($driverId);
        return $this->driver($row, $capabilities);
    }

    public function vehicleDetailUnchecked(AuthenticatedPrincipal $actor, string $vehicleId): array
    {
        return $this->vehicle((array) $this->fleet->findVehicle($actor->hqId, $vehicleId));
    }
}
