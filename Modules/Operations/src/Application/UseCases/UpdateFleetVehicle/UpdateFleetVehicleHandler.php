<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetVehicle;

use Modules\Operations\Domain\FleetWriteConflict;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateFleetVehicleHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
        private \Modules\Operations\Domain\FleetPolicy $fleetPolicy,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\FleetChangeRecorder $fleetChangeRecorder,
        private \Modules\Operations\Application\Services\FleetFailure $fleetFailure,
    )
    {
    }

    public function handle(UpdateFleetVehicleCommand $command): UpdateFleetVehicleResult
    {
        return new UpdateFleetVehicleResult($this->execute($command->actor, $command->vehicleId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $vehicleId, array $input, string $correlationId): array
    {
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.manage');
        if (array_key_exists('home_node_id', $input)) {
            $this->fleetAccessGuard->activeNode($actor, (string) $input['home_node_id']);
        }
        try {
            return $this->transactions->run(function () use ($actor, $vehicleId, $input, $correlationId): array {
                $row = $this->fleet->findVehicleForUpdate($actor->hqId, $vehicleId);
                if ($row === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Vehicle not found.');
                }
                $this->fleetAccessGuard->assertScopeNode($actor, (string) $row->home_node_id, 'fleet.vehicle.manage');
                if (isset($input['home_node_id'])) {
                    $this->fleetAccessGuard->assertScopeNode($actor, $input['home_node_id'], 'fleet.vehicle.manage');
                }
                $expected = (int) $input['expected_version'];
                if ((int) $row->version !== $expected) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Vehicle changed since it was loaded.', details: ['current_version' => (int) $row->version]);
                }
                $before = $this->fleetProjection->vehicle((array) $row);
                $status = (string) ($input['status'] ?? $row->status);
                $availability = (string) ($input['availability_status'] ?? $row->availability_status);
                [$status, $availability] = $this->fleetPolicy->lifecycle($status, $availability, array_key_exists('status', $input));
                $plate = array_key_exists('plate_number', $input) ? trim((string) $input['plate_number']) : (string) $row->plate_number;
                $this->fleet->updateVehicle($actor->hqId, $vehicleId, $expected, [
                    'plate_number' => $plate,
                    'registration_number' => $plate,
                    'vehicle_type' => $input['vehicle_type'] ?? $row->vehicle_type,
                    'home_node_id' => $input['home_node_id'] ?? $row->home_node_id,
                    'capacity_weight_grams' => array_key_exists('capacity_weight_grams', $input) ? $input['capacity_weight_grams'] : $row->capacity_weight_grams,
                    'capacity_volume_cm3' => array_key_exists('capacity_volume_cm3', $input) ? $input['capacity_volume_cm3'] : $row->capacity_volume_cm3,
                    'status' => $status,
                    'availability_status' => $availability,
                    'version' => $expected + 1,
                    'updated_at' => $this->clock->now(),
                ]);
                $after = $this->fleetProjection->vehicleDetailUnchecked($actor, $vehicleId);
                $this->fleetChangeRecorder->record($actor, 'FLEET_VEHICLE_UPDATED', 'VEHICLE', $vehicleId, $status, $correlationId, $before, $after);
                return $after;
            });
        } catch (FleetWriteConflict $exception) {
            $this->fleetFailure->rethrowConflict($exception, 'The plate number is already used by another Vehicle.');
        }
    }
}
