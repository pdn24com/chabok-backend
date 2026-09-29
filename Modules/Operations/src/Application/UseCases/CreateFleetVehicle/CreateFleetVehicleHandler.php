<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetVehicle;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Contracts\FleetChangeRecorderInterface;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final readonly class CreateFleetVehicleHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private FleetChangeRecorderInterface $fleetChangeRecorder,
    ) {}

    public function handle(CreateFleetVehicleCommand $command): VehicleRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.manage');
        $this->fleetAccessGuard->activeNode($actor, $input->homeNodeId);
        $this->fleetAccessGuard->assertScopeNode($actor, $input->homeNodeId, 'fleet.vehicle.manage');
        try {
            return $this->connection->transaction(function () use ($actor, $input, $correlationId): VehicleRecord {
                $plate = $input->plateNumber;
                $now = $this->clock->now();
                $vehicle = new VehicleRecord;
                $vehicle->forceFill([

                    'hq_id' => $actor->hqId,
                    'vehicle_code' => mb_strtoupper($input->vehicleCode),
                    'registration_number' => $plate,
                    'plate_number' => $plate,
                    'vehicle_type' => $input->vehicleType,
                    'home_node_id' => $input->homeNodeId,
                    'capacity_weight_grams' => $input->capacityWeightGrams ?? null,
                    'capacity_volume_cm3' => $input->capacityVolumeCm3 ?? null,
                    'status' => 'ACTIVE',
                    'availability_status' => 'AVAILABLE',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $vehicle->save();
                $id = (string) $vehicle->getKey();
                $after = $vehicle;
                $this->fleetChangeRecorder->record($actor, 'FLEET_VEHICLE_CREATED', 'VEHICLE', $id, 'ACTIVE', $correlationId, null, $after);

                return $after;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.vehicle_code_plate_number_already_exists');
        }
    }
}
