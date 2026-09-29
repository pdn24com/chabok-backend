<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\DriverChangesDto;
use Modules\Operations\Application\Dto\DriverCreationDto;
use Modules\Operations\Application\Dto\FleetFiltersDto;
use Modules\Operations\Application\Dto\VehicleChangesDto;
use Modules\Operations\Application\Dto\VehicleCreationDto;
use Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverCommand;
use Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverHandler;
use Modules\Operations\Application\UseCases\CreateFleetVehicle\CreateFleetVehicleCommand;
use Modules\Operations\Application\UseCases\CreateFleetVehicle\CreateFleetVehicleHandler;
use Modules\Operations\Application\UseCases\GetFleetDriver\GetFleetDriverCommand;
use Modules\Operations\Application\UseCases\GetFleetDriver\GetFleetDriverHandler;
use Modules\Operations\Application\UseCases\GetFleetVehicle\GetFleetVehicleCommand;
use Modules\Operations\Application\UseCases\GetFleetVehicle\GetFleetVehicleHandler;
use Modules\Operations\Application\UseCases\ListFleetDrivers\ListFleetDriversCommand;
use Modules\Operations\Application\UseCases\ListFleetDrivers\ListFleetDriversHandler;
use Modules\Operations\Application\UseCases\ListFleetVehicles\ListFleetVehiclesCommand;
use Modules\Operations\Application\UseCases\ListFleetVehicles\ListFleetVehiclesHandler;
use Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverCommand;
use Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverHandler;
use Modules\Operations\Application\UseCases\UpdateFleetVehicle\UpdateFleetVehicleCommand;
use Modules\Operations\Application\UseCases\UpdateFleetVehicle\UpdateFleetVehicleHandler;
use Modules\Operations\Presentation\Http\Resources\FleetDriverResource;
use Modules\Operations\Presentation\Http\Resources\FleetVehicleResource;

/** Test fixture shorthand for focused use cases; no production facade. */
final readonly class FleetAdministrationFixtures
{
    public function __construct(
        private ListFleetDriversHandler $listFleetDrivers,
        private GetFleetDriverHandler $getFleetDriver,
        private CreateFleetDriverHandler $createFleetDriver,
        private UpdateFleetDriverHandler $updateFleetDriver,
        private ListFleetVehiclesHandler $listFleetVehicles,
        private GetFleetVehicleHandler $getFleetVehicle,
        private CreateFleetVehicleHandler $createFleetVehicle,
        private UpdateFleetVehicleHandler $updateFleetVehicle,
    ) {}

    public function drivers(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listFleetDrivers->handle(new ListFleetDriversCommand($actor, FleetFiltersDto::fromValidated($filters)))->through(fn ($row) => (new FleetDriverResource($row))->resolve());
    }

    public function driverDetail(AuthenticatedPrincipal $actor, string $driverId): array
    {
        return (new FleetDriverResource($this->getFleetDriver->handle(new GetFleetDriverCommand($actor, $driverId))))->resolve();
    }

    public function createDriver(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new FleetDriverResource($this->createFleetDriver->handle(new CreateFleetDriverCommand($actor, DriverCreationDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function updateDriver(
        AuthenticatedPrincipal $actor,
        string $driverId,
        array $input,
        string $correlationId,
    ): array {
        return (new FleetDriverResource($this->updateFleetDriver->handle(new UpdateFleetDriverCommand($actor, $driverId, DriverChangesDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function vehicles(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listFleetVehicles->handle(new ListFleetVehiclesCommand($actor, FleetFiltersDto::fromValidated($filters)))->through(fn ($row) => (new FleetVehicleResource($row))->resolve());
    }

    public function vehicleDetail(AuthenticatedPrincipal $actor, string $vehicleId): array
    {
        return (new FleetVehicleResource($this->getFleetVehicle->handle(new GetFleetVehicleCommand($actor, $vehicleId))))->resolve();
    }

    public function createVehicle(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new FleetVehicleResource($this->createFleetVehicle->handle(new CreateFleetVehicleCommand($actor, VehicleCreationDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function updateVehicle(
        AuthenticatedPrincipal $actor,
        string $vehicleId,
        array $input,
        string $correlationId,
    ): array {
        return (new FleetVehicleResource($this->updateFleetVehicle->handle(new UpdateFleetVehicleCommand($actor, $vehicleId, VehicleChangesDto::fromValidated($input), $correlationId))))->resolve();
    }
}
