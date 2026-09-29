<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskCommand;
use Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskHandler;
use Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskCommand;
use Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskHandler;
use Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskCommand;
use Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskHandler;
use Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskCommand;
use Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskHandler;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler;
use Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksCommand;
use Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksHandler;
use Modules\Operations\Presentation\Http\Requests\AssignPickupTaskRequest;
use Modules\Operations\Presentation\Http\Requests\CompletePickupTaskRequest;
use Modules\Operations\Presentation\Http\Requests\CreatePickupTaskRequest;
use Modules\Operations\Presentation\Http\Requests\FailPickupTaskRequest;
use Modules\Operations\Presentation\Http\Resources\PickupTaskResource;

final class PickupTaskController
{
    public function index(Request $request, ListPickupTasksHandler $listPickupTasksHandler): JsonResponse
    {
        return ApiResponder::success($request, PickupTaskResource::collection($listPickupTasksHandler->handle(new ListPickupTasksCommand($request->attributes->get('principal'), $this->node($request))))->resolve($request));
    }

    public function store(CreatePickupTaskRequest $request, CreatePickupTaskHandler $createPickupTaskHandler): JsonResponse
    {
        $in = $request->validated();

        return ApiResponder::success($request, (new PickupTaskResource($createPickupTaskHandler->handle(new CreatePickupTaskCommand($request->attributes->get('principal'), $this->node($request), (string) $in['consignment_id'], (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function show(Request $request, GetPickupTaskHandler $getPickupTaskHandler, string $id): JsonResponse
    {
        return ApiResponder::success($request, (new PickupTaskResource($getPickupTaskHandler->handle(new GetPickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id))))->resolve($request));
    }

    public function assign(AssignPickupTaskRequest $request, AssignPickupTaskHandler $assignPickupTaskHandler, string $id): JsonResponse
    {
        $in = $request->validated();

        return ApiResponder::success($request, (new PickupTaskResource($assignPickupTaskHandler->handle(new AssignPickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id, (string) $in['driver_id'], (int) $in['expected_version'], (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function complete(CompletePickupTaskRequest $request, CompletePickupTaskHandler $completePickupTaskHandler, string $id): JsonResponse
    {
        return ApiResponder::success($request, (new PickupTaskResource($completePickupTaskHandler->handle(new CompletePickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id, (int) $request->validated()['expected_version'], (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function fail(FailPickupTaskRequest $request, FailPickupTaskHandler $failPickupTaskHandler, string $id): never
    {
        $in = $request->validated();

        $failPickupTaskHandler->handle(new FailPickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id, (int) $in['expected_version'], (string) $in['reason_code'], (string) $in['reason'], (string) $request->attributes->get('correlation_id')));
    }

    private function node(Request $r): string
    {
        $id = $r->attributes->get('node_id');
        if (! is_string($id) || $id === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required');
        }

        return $id;
    }
}
