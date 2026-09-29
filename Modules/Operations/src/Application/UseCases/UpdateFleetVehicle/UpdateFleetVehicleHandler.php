<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetVehicle;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Contracts\FleetChangeRecorderInterface;
use Modules\Operations\Application\Repositories\VehicleRepositoryInterface;
use Modules\Operations\Domain\Policies\FleetPolicy;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final readonly class UpdateFleetVehicleHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private ConnectionInterface $connection,
        private FleetPolicy $fleetPolicy,
        private ClockInterface $clock,
        private FleetChangeRecorderInterface $fleetChangeRecorder,
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function handle(UpdateFleetVehicleCommand $command): VehicleRecord
    {
        $actor = $command->actor;
        $vehicleId = $command->vehicleId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.manage');
        if ($input->homeNodeId !== null) {
            $this->fleetAccessGuard->activeNode($actor, $input->homeNodeId);
        }
        try {
            return $this->connection->transaction(function () use ($actor, $vehicleId, $input, $correlationId): VehicleRecord {
                $row = $this->vehicleRepository->lockByTenant($actor->hqId, $vehicleId);
                if ($row === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'operations.vehicle_not_found');
                }
                $this->fleetAccessGuard->assertScopeNode($actor, $row->home_node_id, 'fleet.vehicle.manage');
                if ($input->homeNodeId !== null) {
                    $this->fleetAccessGuard->assertScopeNode($actor, $input->homeNodeId, 'fleet.vehicle.manage');
                }
                $expected = $input->expectedVersion;
                if ($row->version !== $expected) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.vehicle_changed_since_loaded', details: ['current_version' => $row->version]);
                }
                $before = clone $row;
                $status = $input->status ?? $row->status;
                $availability = $input->availabilityStatus ?? $row->availability_status;
                $lifecycle = $this->fleetPolicy->lifecycle($status, $availability, $input->status !== null);
                $plate = $input->plateNumber !== null ? $input->plateNumber : (string) $row->plate_number;
                $row->forceFill([
                    'plate_number' => $plate,
                    'registration_number' => $plate,
                    'vehicle_type' => $input->vehicleType ?? $row->vehicle_type,
                    'home_node_id' => $input->homeNodeId ?? $row->home_node_id,
                    'capacity_weight_grams' => $input->capacityWeightProvided ? $input->capacityWeightGrams : $row->capacity_weight_grams,
                    'capacity_volume_cm3' => $input->capacityVolumeProvided ? $input->capacityVolumeCm3 : $row->capacity_volume_cm3,
                    'status' => $lifecycle->status,
                    'availability_status' => $lifecycle->availability,
                    'version' => $expected + 1,
                    'updated_at' => $this->clock->now(),
                ])->save();
                $after = $row;
                $this->fleetChangeRecorder->record($actor, 'FLEET_VEHICLE_UPDATED', 'VEHICLE', $vehicleId, $lifecycle->status->value, $correlationId, $before, $after);

                return $after;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.plate_number_is_already_used_another_vehicle');
        }
    }
}
