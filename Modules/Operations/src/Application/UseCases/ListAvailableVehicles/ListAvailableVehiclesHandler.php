<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableVehicles;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListAvailableVehiclesHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\OperationalDirectoryAccess $operationalDirectoryAccess,
        private \Modules\Operations\Application\Repositories\OperationalDirectoryRepository $directory,
    )
    {
    }

    public function handle(ListAvailableVehiclesCommand $command): ListAvailableVehiclesResult
    {
        return new ListAvailableVehiclesResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->operationalDirectoryAccess->access($actor, $nodeId, 'driver.view', 'Driver');
        return $this->directory->vehicles($actor->hqId, $nodeId);
    }
}
