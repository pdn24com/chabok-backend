<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetVehicles;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Repositories\VehicleRepositoryInterface;

final readonly class ListFleetVehiclesHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function handle(ListFleetVehiclesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.view');

        return $this->vehicleRepository->search($actor->hqId, $this->fleetAccessGuard->scopeNodes($actor, 'fleet.vehicle.view'), $filters);
    }
}
