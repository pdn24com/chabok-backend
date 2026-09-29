<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableDrivers;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\OperationalDirectoryAccessInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final readonly class ListAvailableDriversHandler
{
    public function __construct(
        private OperationalDirectoryAccessInterface $operationalDirectoryAccess,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    /** @return Collection<int, DriverRecord> */
    public function handle(ListAvailableDriversCommand $command): Collection
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $capability = $command->capability;
        $this->operationalDirectoryAccess->access($actor, $nodeId, 'driver.view', 'Driver');

        return $this->driverRepository->availableAtNode($actor->hqId, $nodeId, $capability?->value);
    }
}
