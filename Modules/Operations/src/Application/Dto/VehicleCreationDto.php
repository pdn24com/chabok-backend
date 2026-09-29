<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\VehicleType;

final readonly class VehicleCreationDto
{
    public function __construct(
        public string $vehicleCode,
        public string $plateNumber,
        public VehicleType $vehicleType,
        public string $homeNodeId,
        public ?int $capacityWeightGrams,
        public ?int $capacityVolumeCm3,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            vehicleCode: trim($input['vehicle_code']),
            plateNumber: trim($input['plate_number']),
            vehicleType: VehicleType::from($input['vehicle_type']),
            homeNodeId: $input['home_node_id'],
            capacityWeightGrams: isset($input['capacity_weight_grams']) ? (int) $input['capacity_weight_grams'] : null,
            capacityVolumeCm3: isset($input['capacity_volume_cm3']) ? (int) $input['capacity_volume_cm3'] : null,
        );
    }
}
