<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Operations\Application\Dto\DeliveryTaskFiltersDto;
use Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksCommand;
use Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksHandler;
use Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskHandler;
use Modules\Operations\Presentation\Http\Requests\AssignDeliveryTaskRequest;
use Modules\Operations\Presentation\Http\Requests\CompleteDeliveryTaskRequest;
use Modules\Operations\Presentation\Http\Requests\FailDeliveryTaskRequest;
use Modules\Operations\Presentation\Http\Requests\ListDeliveryTasksRequest;
use Modules\Operations\Presentation\Http\Requests\RetryDeliveryTaskRequest;
use Modules\Operations\Presentation\Http\Resources\DeliveryTaskDetailResource;
use Modules\Operations\Presentation\Http\Resources\DeliveryTaskResource;

final class DeliveryTaskController
{
    public function index(ListDeliveryTasksRequest $r, ListDeliveryTasksHandler $listDeliveryTasksHandler): JsonResponse
    {
        $filters = $r->validated();

        return ApiResponder::success($r, DeliveryTaskResource::collection($listDeliveryTasksHandler->handle(new ListDeliveryTasksCommand($r->attributes->get('principal'), $this->node($r), DeliveryTaskFiltersDto::fromValidated($filters))))->resolve($r));
    }

    public function show(Request $r, GetDeliveryTaskHandler $getDeliveryTaskHandler, string $id): JsonResponse
    {
        return ApiResponder::success($r, (new DeliveryTaskDetailResource($getDeliveryTaskHandler->handle(new GetDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id))))->resolve($r));
    }

    public function assign(AssignDeliveryTaskRequest $r, AssignDeliveryTaskHandler $assignDeliveryTaskHandler, string $id): JsonResponse
    {
        $in = $r->validated();

        return ApiResponder::success($r, (new DeliveryTaskDetailResource($assignDeliveryTaskHandler->handle(new AssignDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (string) $in['driver_id'], (int) $in['expected_version'], (string) $r->attributes->get('correlation_id')))))->resolve($r));
    }

    public function complete(CompleteDeliveryTaskRequest $r, CompleteDeliveryTaskHandler $completeDeliveryTaskHandler, string $id): JsonResponse
    {
        $in = $r->validated();

        return ApiResponder::success($r, (new DeliveryTaskDetailResource($completeDeliveryTaskHandler->handle(new CompleteDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (int) $in['expected_version'], (string) $in['recipient_name'], (string) $in['delivered_at'], $in['note'] ?? null, (string) $r->attributes->get('correlation_id')))))->resolve($r));
    }

    public function fail(FailDeliveryTaskRequest $r, FailDeliveryTaskHandler $failDeliveryTaskHandler, string $id): never
    {
        $in = $r->validated();

        $failDeliveryTaskHandler->handle(new FailDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (int) $in['expected_version'], (string) $in['reason_code'], (string) $in['reason'], (string) $r->attributes->get('correlation_id')));
    }

    public function retry(RetryDeliveryTaskRequest $r, RetryDeliveryTaskHandler $retryDeliveryTaskHandler, string $id): JsonResponse
    {
        $in = $r->validated();

        return ApiResponder::success($r, (new DeliveryTaskDetailResource($retryDeliveryTaskHandler->handle(new RetryDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (int) $in['expected_version'], (string) $in['reason'], (string) $r->attributes->get('correlation_id')))))->resolve($r));
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
