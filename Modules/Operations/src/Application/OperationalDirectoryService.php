<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationalDirectoryService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversHandler $listAvailableDrivers,
        private \Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesHandler $listAvailableVehicles,
        private \Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesHandler $listOperationalRoutes,
        private \Modules\Operations\Application\UseCases\CreateOperationalRoute\CreateOperationalRouteHandler $createOperationalRoute,
    )
    {
    }

    public function drivers(AuthenticatedPrincipal $actor, string $nodeId, ?string $capability): array
    {
        return $this->listAvailableDrivers->handle(new \Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversCommand($actor, $nodeId, $capability))->data;
    }

    public function vehicles(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->listAvailableVehicles->handle(new \Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesCommand($actor, $nodeId))->data;
    }

    public function routes(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->listOperationalRoutes->handle(new \Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesCommand($actor, $nodeId))->data;
    }

    public function createRoute(string $hqId, string $code, string $title, array $legs): string
    {
        return $this->createOperationalRoute->handle(new \Modules\Operations\Application\UseCases\CreateOperationalRoute\CreateOperationalRouteCommand($hqId, $code, $title, $legs))->data;
    }
}
