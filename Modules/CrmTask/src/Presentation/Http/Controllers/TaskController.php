<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmTask\Application\UseCases\AssignTask\AssignTaskHandler;
use Modules\CrmTask\Application\UseCases\CompleteTask\CompleteTaskHandler;
use Modules\CrmTask\Application\UseCases\CreateTask\CreateTaskHandler;
use Modules\CrmTask\Application\UseCases\ListTasks\ListTasksHandler;
use Modules\CrmTask\Application\UseCases\RecordTaskAction\RecordTaskActionHandler;
use Modules\CrmTask\Presentation\Http\Requests\AssignTaskRequest;
use Modules\CrmTask\Presentation\Http\Requests\CompleteTaskRequest;
use Modules\CrmTask\Presentation\Http\Requests\CreateTaskRequest;
use Modules\CrmTask\Presentation\Http\Requests\ListTasksRequest;
use Modules\CrmTask\Presentation\Http\Requests\RecordTaskActionRequest;
use Modules\CrmTask\Presentation\Http\Resources\ActivityResource;
use Modules\CrmTask\Presentation\Http\Resources\TaskAssignmentEventResource;
use Modules\CrmTask\Presentation\Http\Resources\TaskResource;
use Modules\CrmTask\Presentation\Mappers\TaskCommandMapper;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The task inbox: the queue, raising work, moving it, recording what happened, and finishing it. */
final class TaskController
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function index(ListTasksRequest $request, ListTasksHandler $handler): JsonResponse
    {
        $result = $handler->handle(TaskCommandMapper::listing($request->attributes->get('principal'), $request->validated()));
        $now = $this->clock->now();

        return ApiResponder::success($request, [
            'summary' => [
                'open' => $result->summary->open,
                'waiting_customer' => $result->summary->waitingCustomer,
                'today' => $result->summary->today,
                'overdue' => $result->summary->overdue,
                'internal' => $result->summary->internal,
            ],
            'items' => $result->tasks
                ->map(fn ($task): array => (new TaskResource($task, $now, $result->customerNames, $result->opportunityTitles))->resolve($request))
                ->all(),
        ]);
    }

    public function store(CreateTaskRequest $request, CreateTaskHandler $handler): JsonResponse
    {
        $result = $handler->handle(TaskCommandMapper::draft($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, [
            'task' => (new TaskResource($result->task, $this->clock->now()))->resolve($request),
            'assignment_event' => (new TaskAssignmentEventResource($result->assignmentEvent))->resolve($request),
        ], status: 201);
    }

    public function complete(CompleteTaskRequest $request, CompleteTaskHandler $handler, string $taskId): JsonResponse
    {
        $result = $handler->handle(TaskCommandMapper::completion($request->attributes->get('principal'), $taskId, $request->validated()));

        return ApiResponder::success($request, new TaskResource($result->task, $this->clock->now()));
    }

    public function assign(AssignTaskRequest $request, AssignTaskHandler $handler, string $taskId): JsonResponse
    {
        $result = $handler->handle(TaskCommandMapper::assignment($request->attributes->get('principal'), $taskId, $request->validated()));

        return ApiResponder::success($request, [
            'assignment_event' => (new TaskAssignmentEventResource($result->assignmentEvent))->resolve($request),
            'task' => (new TaskResource($result->task, $this->clock->now()))->resolve($request),
        ], status: 201);
    }

    public function recordAction(RecordTaskActionRequest $request, RecordTaskActionHandler $handler, string $taskId): JsonResponse
    {
        $result = $handler->handle(TaskCommandMapper::action($request->attributes->get('principal'), $taskId, $request->validated()));

        return ApiResponder::success($request, [
            'activity' => (new ActivityResource($result->activity))->resolve($request),
            'task' => (new TaskResource($result->task, $this->clock->now()))->resolve($request),
        ], status: 201);
    }
}
