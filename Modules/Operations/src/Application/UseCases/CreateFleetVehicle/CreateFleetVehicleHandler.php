<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetVehicle;

use Modules\Operations\Domain\FleetWriteConflict;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateFleetVehicleHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
        private \Modules\Operations\Application\Services\FleetChangeRecorder $fleetChangeRecorder,
        private \Modules\Operations\Application\Services\FleetFailure $fleetFailure,
    )
    {
    }

    public function handle(CreateFleetVehicleCommand $command): CreateFleetVehicleResult
    {
        return new CreateFleetVehicleResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.manage');
        $this->fleetAccessGuard->activeNode($actor, (string) $input['home_node_id']);
        $this->fleetAccessGuard->assertScopeNode($actor, (string) $input['home_node_id'], 'fleet.vehicle.manage');
        try {
            return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
                $id = $this->identifiers->uuid();
                $plate = trim((string) $input['plate_number']);
                $now = $this->clock->now();
                $this->fleet->insertVehicle([
                    'vehicle_id' => $id,
                    'hq_id' => $actor->hqId,
                    'vehicle_code' => mb_strtoupper(trim((string) $input['vehicle_code'])),
                    'registration_number' => $plate,
                    'plate_number' => $plate,
                    'vehicle_type' => $input['vehicle_type'],
                    'home_node_id' => $input['home_node_id'],
                    'capacity_weight_grams' => $input['capacity_weight_grams'] ?? null,
                    'capacity_volume_cm3' => $input['capacity_volume_cm3'] ?? null,
                    'status' => 'ACTIVE',
                    'availability_status' => 'AVAILABLE',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $after = $this->fleetProjection->vehicleDetailUnchecked($actor, $id);
                $this->fleetChangeRecorder->record($actor, 'FLEET_VEHICLE_CREATED', 'VEHICLE', $id, 'ACTIVE', $correlationId, null, $after);
                return $after;
            });
        } catch (FleetWriteConflict $exception) {
            $this->fleetFailure->rethrowConflict($exception, 'Vehicle code or plate number already exists.');
        }
    }
}
