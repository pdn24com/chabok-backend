<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversCommand;
use Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversHandler;
use Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesCommand;
use Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesHandler;
use Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesCommand;
use Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesHandler;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Presentation\Http\Requests\ListAvailableDriversRequest;
use Modules\Operations\Presentation\Http\Resources\AvailableDriverResource;
use Modules\Operations\Presentation\Http\Resources\AvailableVehicleResource;
use Modules\Operations\Presentation\Http\Resources\OperationalRouteResource;

final class OperationalDirectoryController
{
    public function drivers(ListAvailableDriversRequest $request, ListAvailableDriversHandler $listAvailableDriversHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, AvailableDriverResource::collection($listAvailableDriversHandler->handle(new ListAvailableDriversCommand($request->attributes->get('principal'), $this->node($request), isset($input['capability']) ? DriverCapability::from($input['capability']) : null)))->resolve($request));
    }

    public function vehicles(Request $request, ListAvailableVehiclesHandler $listAvailableVehiclesHandler): JsonResponse
    {
        return ApiResponder::success($request, AvailableVehicleResource::collection($listAvailableVehiclesHandler->handle(new ListAvailableVehiclesCommand($request->attributes->get('principal'), $this->node($request))))->resolve($request));
    }

    public function routes(Request $request, ListOperationalRoutesHandler $listOperationalRoutesHandler): JsonResponse
    {
        return ApiResponder::success($request, OperationalRouteResource::collection($listOperationalRoutesHandler->handle(new ListOperationalRoutesCommand($request->attributes->get('principal'), $this->node($request))))->resolve($request));
    }

    private function node(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required');
        }

        return $nodeId;
    }
}
