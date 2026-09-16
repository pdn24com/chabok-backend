<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class OperationalDirectoryController
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversHandler $drivers,
        private \Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesHandler $vehicles,
        private \Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesHandler $routes,
    )
    {
    }

    public function drivers(\Modules\Operations\Presentation\Http\Requests\ListAvailableDriversRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->drivers->handle(new \Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversCommand($request->attributes->get('principal'), $this->node($request), $input['capability'] ?? null))->data);
    }

    public function vehicles(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->vehicles->handle(new \Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesCommand($request->attributes->get('principal'), $this->node($request)))->data);
    }

    public function routes(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->routes->handle(new \Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesCommand($request->attributes->get('principal'), $this->node($request)))->data);
    }

    private function node(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (!is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        }
        return $nodeId;
    }
}
