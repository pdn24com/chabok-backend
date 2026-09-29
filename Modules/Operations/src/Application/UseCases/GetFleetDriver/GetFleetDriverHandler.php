<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetDriver;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final readonly class GetFleetDriverHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function handle(GetFleetDriverCommand $command): DriverRecord
    {
        $actor = $command->actor;
        $driverId = $command->driverId;
        $this->fleetAccessGuard->access($actor, 'fleet.driver.view');
        $row = $this->driverRepository->findWithCapabilities($actor->hqId, $driverId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'operations.driver_not_found');
        }
        $this->fleetAccessGuard->assertScopeNode($actor, $row->home_node_id, 'fleet.driver.view');

        return $row;
    }
}
