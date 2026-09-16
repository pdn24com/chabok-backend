<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetDriver;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetFleetDriverHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
    )
    {
    }

    public function handle(GetFleetDriverCommand $command): GetFleetDriverResult
    {
        return new GetFleetDriverResult($this->execute($command->actor, $command->driverId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $driverId): array
    {
        $this->fleetAccessGuard->access($actor, 'fleet.driver.view');
        $row = $this->fleet->findDriver($actor->hqId, $driverId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Driver not found.');
        }
        $this->fleetAccessGuard->assertScopeNode($actor, (string) $row->home_node_id, 'fleet.driver.view');
        return $this->fleetProjection->driver((array) $row, $this->fleet->driverCapabilities($driverId));
    }
}
