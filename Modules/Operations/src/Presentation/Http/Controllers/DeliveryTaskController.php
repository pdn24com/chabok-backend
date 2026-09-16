<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class DeliveryTaskController
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksHandler $list,
        private \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler $get,
        private \Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskHandler $assign,
        private \Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskHandler $complete,
        private \Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskHandler $fail,
        private \Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskHandler $retry,
    )
    {
    }

    public function index(\Modules\Operations\Presentation\Http\Requests\ListDeliveryTasksRequest $r): JsonResponse
    {
        $filters = $r->validated();
        return ApiResponder::success($r, $this->list->handle(new \Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksCommand($r->attributes->get('principal'), $this->node($r), $filters))->data);
    }

    public function show(Request $r, string $id): JsonResponse
    {
        return ApiResponder::success($r, $this->get->handle(new \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id))->data);
    }

    public function assign(\Modules\Operations\Presentation\Http\Requests\AssignDeliveryTaskRequest $r, string $id): JsonResponse
    {
        $in = $r->validated();
        return ApiResponder::success($r, $this->assign->handle(new \Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (string) $in['driver_id'], (int) $in['expected_version'], (string) $r->attributes->get('correlation_id')))->data);
    }

    public function complete(\Modules\Operations\Presentation\Http\Requests\CompleteDeliveryTaskRequest $r, string $id): JsonResponse
    {
        $in = $r->validated();
        return ApiResponder::success($r, $this->complete->handle(new \Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (int) $in['expected_version'], (string) $in['recipient_name'], (string) $in['delivered_at'], $in['note'] ?? null, (string) $r->attributes->get('correlation_id')))->data);
    }

    public function fail(\Modules\Operations\Presentation\Http\Requests\FailDeliveryTaskRequest $r, string $id): JsonResponse
    {
        $in = $r->validated();
        return ApiResponder::success($r, $this->fail->handle(new \Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (int) $in['expected_version'], (string) $in['reason_code'], (string) $in['reason'], (string) $r->attributes->get('correlation_id')))->data);
    }

    public function retry(\Modules\Operations\Presentation\Http\Requests\RetryDeliveryTaskRequest $r, string $id): JsonResponse
    {
        $in = $r->validated();
        return ApiResponder::success($r, $this->retry->handle(new \Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskCommand($r->attributes->get('principal'), $this->node($r), $id, (int) $in['expected_version'], (string) $in['reason'], (string) $r->attributes->get('correlation_id')))->data);
    }

    private function node(Request $r): string
    {
        $id = $r->attributes->get('node_id');
        if (!is_string($id) || $id === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        }
        return $id;
    }
}
