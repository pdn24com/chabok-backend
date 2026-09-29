<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetVehicle;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Repositories\VehicleRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final readonly class GetFleetVehicleHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function handle(GetFleetVehicleCommand $command): VehicleRecord
    {
        $actor = $command->actor;
        $vehicleId = $command->vehicleId;
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.view');
        $row = $this->vehicleRepository->findByTenant($actor->hqId, $vehicleId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'operations.vehicle_not_found');
        }
        $this->fleetAccessGuard->assertScopeNode($actor, $row->home_node_id, 'fleet.vehicle.view');

        return $row;
    }
}
