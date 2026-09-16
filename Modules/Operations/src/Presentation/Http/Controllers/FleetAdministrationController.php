<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class FleetAdministrationController
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListFleetDrivers\ListFleetDriversHandler $drivers,
        private \Modules\Operations\Application\UseCases\GetFleetDriver\GetFleetDriverHandler $driverDetail,
        private \Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverHandler $createDriver,
        private \Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverHandler $updateDriver,
        private \Modules\Operations\Application\UseCases\ListFleetVehicles\ListFleetVehiclesHandler $vehicles,
        private \Modules\Operations\Application\UseCases\GetFleetVehicle\GetFleetVehicleHandler $vehicleDetail,
        private \Modules\Operations\Application\UseCases\CreateFleetVehicle\CreateFleetVehicleHandler $createVehicle,
        private \Modules\Operations\Application\UseCases\UpdateFleetVehicle\UpdateFleetVehicleHandler $updateVehicle,
    )
    {
    }

    public function drivers(\Modules\Operations\Presentation\Http\Requests\ListFleetDriversRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->drivers->handle(new \Modules\Operations\Application\UseCases\ListFleetDrivers\ListFleetDriversCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), $this->pagination($page));
    }

    public function createDriver(\Modules\Operations\Presentation\Http\Requests\CreateDriverRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createDriver->handle(new \Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function driver(Request $request, string $driver_id): JsonResponse
    {
        return ApiResponder::success($request, $this->driverDetail->handle(new \Modules\Operations\Application\UseCases\GetFleetDriver\GetFleetDriverCommand($this->principal($request), $driver_id))->data);
    }

    public function updateDriver(\Modules\Operations\Presentation\Http\Requests\UpdateDriverRequest $request, string $driver_id): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateDriver->handle(new \Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverCommand($this->principal($request), $driver_id, $input, $this->correlation($request)))->data);
    }

    public function vehicles(\Modules\Operations\Presentation\Http\Requests\ListFleetVehiclesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->vehicles->handle(new \Modules\Operations\Application\UseCases\ListFleetVehicles\ListFleetVehiclesCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), $this->pagination($page));
    }

    public function createVehicle(\Modules\Operations\Presentation\Http\Requests\CreateVehicleRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createVehicle->handle(new \Modules\Operations\Application\UseCases\CreateFleetVehicle\CreateFleetVehicleCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function vehicle(Request $request, string $vehicle_id): JsonResponse
    {
        return ApiResponder::success($request, $this->vehicleDetail->handle(new \Modules\Operations\Application\UseCases\GetFleetVehicle\GetFleetVehicleCommand($this->principal($request), $vehicle_id))->data);
    }

    public function updateVehicle(\Modules\Operations\Presentation\Http\Requests\UpdateVehicleRequest $request, string $vehicle_id): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateVehicle->handle(new \Modules\Operations\Application\UseCases\UpdateFleetVehicle\UpdateFleetVehicleCommand($this->principal($request), $vehicle_id, $input, $this->correlation($request)))->data);
    }
    /** @return array<string, mixed> */
    /** @param array<string,mixed> $input */

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
    /** @return array{pagination:array{page:int,page_size:int,total:int,total_pages:int}} */

    private function pagination(\Modules\Foundation\Application\Data\Page $page): array
    {
        return ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]];
    }
}
