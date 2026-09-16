<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class FleetAdministrationService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListFleetDrivers\ListFleetDriversHandler $listFleetDrivers,
        private \Modules\Operations\Application\UseCases\GetFleetDriver\GetFleetDriverHandler $getFleetDriver,
        private \Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverHandler $createFleetDriver,
        private \Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverHandler $updateFleetDriver,
        private \Modules\Operations\Application\UseCases\ListFleetVehicles\ListFleetVehiclesHandler $listFleetVehicles,
        private \Modules\Operations\Application\UseCases\GetFleetVehicle\GetFleetVehicleHandler $getFleetVehicle,
        private \Modules\Operations\Application\UseCases\CreateFleetVehicle\CreateFleetVehicleHandler $createFleetVehicle,
        private \Modules\Operations\Application\UseCases\UpdateFleetVehicle\UpdateFleetVehicleHandler $updateFleetVehicle,
    )
    {
    }

    public function drivers(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listFleetDrivers->handle(new \Modules\Operations\Application\UseCases\ListFleetDrivers\ListFleetDriversCommand($actor, $filters))->data;
    }

    public function driverDetail(AuthenticatedPrincipal $actor, string $driverId): array
    {
        return $this->getFleetDriver->handle(new \Modules\Operations\Application\UseCases\GetFleetDriver\GetFleetDriverCommand($actor, $driverId))->data;
    }

    public function createDriver(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createFleetDriver->handle(new \Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverCommand($actor, $input, $correlationId))->data;
    }

    public function updateDriver(AuthenticatedPrincipal $actor, string $driverId, array $input, string $correlationId): array
    {
        return $this->updateFleetDriver->handle(new \Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverCommand($actor, $driverId, $input, $correlationId))->data;
    }

    public function vehicles(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listFleetVehicles->handle(new \Modules\Operations\Application\UseCases\ListFleetVehicles\ListFleetVehiclesCommand($actor, $filters))->data;
    }

    public function vehicleDetail(AuthenticatedPrincipal $actor, string $vehicleId): array
    {
        return $this->getFleetVehicle->handle(new \Modules\Operations\Application\UseCases\GetFleetVehicle\GetFleetVehicleCommand($actor, $vehicleId))->data;
    }

    public function createVehicle(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createFleetVehicle->handle(new \Modules\Operations\Application\UseCases\CreateFleetVehicle\CreateFleetVehicleCommand($actor, $input, $correlationId))->data;
    }

    public function updateVehicle(AuthenticatedPrincipal $actor, string $vehicleId, array $input, string $correlationId): array
    {
        return $this->updateFleetVehicle->handle(new \Modules\Operations\Application\UseCases\UpdateFleetVehicle\UpdateFleetVehicleCommand($actor, $vehicleId, $input, $correlationId))->data;
    }
}
