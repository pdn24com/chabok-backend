<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableDrivers;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListAvailableDriversHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\OperationalDirectoryAccess $operationalDirectoryAccess,
        private \Modules\Operations\Application\Repositories\OperationalDirectoryRepository $directory,
    )
    {
    }

    public function handle(ListAvailableDriversCommand $command): ListAvailableDriversResult
    {
        return new ListAvailableDriversResult($this->execute($command->actor, $command->nodeId, $command->capability));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, ?string $capability): array
    {
        $this->operationalDirectoryAccess->access($actor, $nodeId, 'driver.view', 'Driver');
        return $this->directory->drivers($actor->hqId, $nodeId, $capability);
    }
}
