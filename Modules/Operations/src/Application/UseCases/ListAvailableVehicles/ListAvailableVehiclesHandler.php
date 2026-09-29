<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableVehicles;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\OperationalDirectoryAccessInterface;
use Modules\Operations\Application\Repositories\VehicleRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final readonly class ListAvailableVehiclesHandler
{
    public function __construct(
        private OperationalDirectoryAccessInterface $operationalDirectoryAccess,
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    /** @return Collection<int, VehicleRecord> */
    public function handle(ListAvailableVehiclesCommand $command): Collection
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->operationalDirectoryAccess->access($actor, $nodeId, 'driver.view', 'Driver');

        return $this->vehicleRepository->availableAtNode($actor->hqId, $nodeId);
    }
}
