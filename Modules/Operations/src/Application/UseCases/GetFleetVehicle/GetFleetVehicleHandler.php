<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetVehicle;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetFleetVehicleHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
    )
    {
    }

    public function handle(GetFleetVehicleCommand $command): GetFleetVehicleResult
    {
        return new GetFleetVehicleResult($this->execute($command->actor, $command->vehicleId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $vehicleId): array
    {
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.view');
        $row = $this->fleet->findVehicle($actor->hqId, $vehicleId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Vehicle not found.');
        }
        $this->fleetAccessGuard->assertScopeNode($actor, (string) $row->home_node_id, 'fleet.vehicle.view');
        return $this->fleetProjection->vehicle((array) $row);
    }
}
