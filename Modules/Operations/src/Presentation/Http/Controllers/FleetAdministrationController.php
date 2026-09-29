<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Presentation\Http\ApiResponder;
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
use Modules\Operations\Presentation\Http\Requests\CreateDriverRequest;
use Modules\Operations\Presentation\Http\Requests\CreateVehicleRequest;
use Modules\Operations\Presentation\Http\Requests\ListFleetDriversRequest;
use Modules\Operations\Presentation\Http\Requests\ListFleetVehiclesRequest;
use Modules\Operations\Presentation\Http\Requests\UpdateDriverRequest;
use Modules\Operations\Presentation\Http\Requests\UpdateVehicleRequest;
use Modules\Operations\Presentation\Http\Resources\FleetDriverResource;
use Modules\Operations\Presentation\Http\Resources\FleetVehicleResource;

final class FleetAdministrationController
{
    public function drivers(ListFleetDriversRequest $request, ListFleetDriversHandler $listFleetDriversHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listFleetDriversHandler->handle(new ListFleetDriversCommand($request->attributes->get('principal'), FleetFiltersDto::fromValidated($filters)));

        return ApiResponder::success($request, FleetDriverResource::collection($page->items())->resolve($request), $this->pagination($page));
    }

    public function createDriver(CreateDriverRequest $request, CreateFleetDriverHandler $createFleetDriverHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new FleetDriverResource($createFleetDriverHandler->handle(new CreateFleetDriverCommand($request->attributes->get('principal'), DriverCreationDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function driver(Request $request, GetFleetDriverHandler $getFleetDriverHandler, string $driver_id): JsonResponse
    {
        return ApiResponder::success($request, (new FleetDriverResource($getFleetDriverHandler->handle(new GetFleetDriverCommand($request->attributes->get('principal'), $driver_id))))->resolve($request));
    }

    public function updateDriver(UpdateDriverRequest $request, UpdateFleetDriverHandler $updateFleetDriverHandler, string $driver_id): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new FleetDriverResource($updateFleetDriverHandler->handle(new UpdateFleetDriverCommand($request->attributes->get('principal'), $driver_id, DriverChangesDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function vehicles(ListFleetVehiclesRequest $request, ListFleetVehiclesHandler $listFleetVehiclesHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listFleetVehiclesHandler->handle(new ListFleetVehiclesCommand($request->attributes->get('principal'), FleetFiltersDto::fromValidated($filters)));

        return ApiResponder::success($request, FleetVehicleResource::collection($page->items())->resolve($request), $this->pagination($page));
    }

    public function createVehicle(CreateVehicleRequest $request, CreateFleetVehicleHandler $createFleetVehicleHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new FleetVehicleResource($createFleetVehicleHandler->handle(new CreateFleetVehicleCommand($request->attributes->get('principal'), VehicleCreationDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function vehicle(Request $request, GetFleetVehicleHandler $getFleetVehicleHandler, string $vehicle_id): JsonResponse
    {
        return ApiResponder::success($request, (new FleetVehicleResource($getFleetVehicleHandler->handle(new GetFleetVehicleCommand($request->attributes->get('principal'), $vehicle_id))))->resolve($request));
    }

    public function updateVehicle(UpdateVehicleRequest $request, UpdateFleetVehicleHandler $updateFleetVehicleHandler, string $vehicle_id): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new FleetVehicleResource($updateFleetVehicleHandler->handle(new UpdateFleetVehicleCommand($request->attributes->get('principal'), $vehicle_id, VehicleChangesDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    /** @return array{pagination:array{page:int,page_size:int,total:int,total_pages:int}} */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ];
    }
}
