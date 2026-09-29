<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class ManifestContextInputDto
{
    public function __construct(
        public int $expectedVersion = 0,
        public ?string $status = null,
        public ?string $contextKey = null,
        public ?string $targetNodeId = null,
        public ?string $assignedDriverId = null,
        public ?string $assignedVehicleId = null,
        public bool $targetNodeProvided = false,
        public bool $driverProvided = false,
        public bool $vehicleProvided = false,
    ) {}

    public static function fromArray(array $input): self
    {
        return new self(
            expectedVersion: (int) ($input['expected_version'] ?? 0),
            status: $input['manifest_status'] ?? null,
            contextKey: $input['context_key'] ?? null,
            targetNodeId: $input['target_node_id'] ?? null,
            assignedDriverId: $input['assigned_driver_id'] ?? null,
            assignedVehicleId: $input['assigned_vehicle_id'] ?? null,
            targetNodeProvided: array_key_exists('target_node_id', $input),
            driverProvided: array_key_exists('assigned_driver_id', $input),
            vehicleProvided: array_key_exists('assigned_vehicle_id', $input),
        );
    }

    public function withDefaults(ManifestRecord $manifest): self
    {
        return new self(
            expectedVersion: $this->expectedVersion,
            status: $this->status ?? $manifest->manifest_status,
            contextKey: $this->contextKey ?? $manifest->context_key,
            targetNodeId: $this->targetNodeProvided ? $this->targetNodeId : $manifest->destination_node_id,
            assignedDriverId: $this->driverProvided ? $this->assignedDriverId : $manifest->assigned_driver_id,
            assignedVehicleId: $this->vehicleProvided ? $this->assignedVehicleId : $manifest->assigned_vehicle_id,
            targetNodeProvided: true,
            driverProvided: true,
            vehicleProvided: true,
        );
    }
}
