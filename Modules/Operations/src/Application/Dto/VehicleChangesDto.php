<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Domain\Enums\VehicleType;

final readonly class VehicleChangesDto
{
    public function __construct(
        public int $expectedVersion,
        public ?string $plateNumber,
        public ?VehicleType $vehicleType,
        public ?string $homeNodeId,
        public ?int $capacityWeightGrams,
        public bool $capacityWeightProvided,
        public ?int $capacityVolumeCm3,
        public bool $capacityVolumeProvided,
        public ?FleetStatus $status,
        public ?FleetAvailability $availabilityStatus,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            expectedVersion: (int) $input['expected_version'],
            plateNumber: isset($input['plate_number']) ? trim($input['plate_number']) : null,
            vehicleType: isset($input['vehicle_type']) && $input['vehicle_type'] !== '' ? VehicleType::from($input['vehicle_type']) : null,
            homeNodeId: $input['home_node_id'] ?? null,
            capacityWeightGrams: isset($input['capacity_weight_grams']) ? (int) $input['capacity_weight_grams'] : null,
            capacityWeightProvided: array_key_exists('capacity_weight_grams', $input),
            capacityVolumeCm3: isset($input['capacity_volume_cm3']) ? (int) $input['capacity_volume_cm3'] : null,
            capacityVolumeProvided: array_key_exists('capacity_volume_cm3', $input),
            status: isset($input['status']) && $input['status'] !== '' ? FleetStatus::from($input['status']) : null,
            availabilityStatus: isset($input['availability_status']) && $input['availability_status'] !== '' ? FleetAvailability::from($input['availability_status']) : null,
        );
    }
}
