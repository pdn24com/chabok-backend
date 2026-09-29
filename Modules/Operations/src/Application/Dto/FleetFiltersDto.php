<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Domain\Enums\VehicleType;

final readonly class FleetFiltersDto
{
    public function __construct(
        public ?FleetStatus $status,
        public ?FleetAvailability $availabilityStatus,
        public ?string $homeNodeId,
        public string $search,
        public bool $unlinked,
        public ?DriverCapability $capability,
        public ?VehicleType $vehicleType,
        public int $perPage,
        public int $page,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            status: isset($input['status']) && $input['status'] !== '' ? FleetStatus::from($input['status']) : null,
            availabilityStatus: isset($input['availability_status']) && $input['availability_status'] !== '' ? FleetAvailability::from($input['availability_status']) : null,
            homeNodeId: $input['home_node_id'] ?? null,
            search: trim($input['search'] ?? ''),
            unlinked: (bool) ($input['unlinked'] ?? false),
            capability: isset($input['capability']) && $input['capability'] !== '' ? DriverCapability::from($input['capability']) : null,
            vehicleType: isset($input['vehicle_type']) && $input['vehicle_type'] !== '' ? VehicleType::from($input['vehicle_type']) : null,
            perPage: min(100, max(1, (int) ($input['per_page'] ?? 20))),
            page: max(1, (int) ($input['page'] ?? 1)),
        );
    }
}
