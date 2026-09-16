<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PickupTaskController
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksHandler $list,
        private \Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskHandler $create,
        private \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler $get,
        private \Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskHandler $assign,
        private \Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskHandler $complete,
        private \Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskHandler $fail,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->list->handle(new \Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksCommand($request->attributes->get('principal'), $this->node($request)))->data);
    }

    public function store(\Modules\Operations\Presentation\Http\Requests\CreatePickupTaskRequest $request): JsonResponse
    {
        $in = $request->validated();
        return ApiResponder::success($request, $this->create->handle(new \Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskCommand($request->attributes->get('principal'), $this->node($request), (string) $in['consignment_id'], $this->correlation($request)))->data, status: 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponder::success($request, $this->get->handle(new \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id))->data);
    }

    public function assign(\Modules\Operations\Presentation\Http\Requests\AssignPickupTaskRequest $request, string $id): JsonResponse
    {
        $in = $request->validated();
        return ApiResponder::success($request, $this->assign->handle(new \Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id, (string) $in['driver_id'], (int) $in['expected_version'], $this->correlation($request)))->data);
    }

    public function complete(\Modules\Operations\Presentation\Http\Requests\CompletePickupTaskRequest $request, string $id): JsonResponse
    {
        return ApiResponder::success($request, $this->complete->handle(new \Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id, (int) $request->validated()['expected_version'], $this->correlation($request)))->data);
    }

    public function fail(\Modules\Operations\Presentation\Http\Requests\FailPickupTaskRequest $request, string $id): JsonResponse
    {
        $in = $request->validated();
        return ApiResponder::success($request, $this->fail->handle(new \Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskCommand($request->attributes->get('principal'), $this->node($request), $id, (int) $in['expected_version'], (string) $in['reason_code'], (string) $in['reason'], $this->correlation($request)))->data);
    }

    private function correlation(Request $r): string
    {
        return (string) $r->attributes->get('correlation_id');
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
