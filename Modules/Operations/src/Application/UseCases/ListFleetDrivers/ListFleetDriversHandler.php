<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetDrivers;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;

final readonly class ListFleetDriversHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function handle(ListFleetDriversCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->fleetAccessGuard->access($actor, 'fleet.driver.view');

        return $this->driverRepository->search($actor->hqId, $this->fleetAccessGuard->scopeNodes($actor, 'fleet.driver.view'), $filters);
    }
}
