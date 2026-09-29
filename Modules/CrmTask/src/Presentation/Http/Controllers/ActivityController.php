<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmTask\Application\UseCases\RecordActivity\RecordActivityHandler;
use Modules\CrmTask\Presentation\Http\Requests\RecordActivityRequest;
use Modules\CrmTask\Presentation\Http\Resources\ActivityDetailResource;
use Modules\CrmTask\Presentation\Mappers\ActivityCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** Interactions with customers that are not the by-product of working a task. */
final class ActivityController
{
    public function store(RecordActivityRequest $request, RecordActivityHandler $handler): JsonResponse
    {
        $result = $handler->handle(ActivityCommandMapper::record($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success(
            $request,
            (new ActivityDetailResource($result->activity, $result->participants))->resolve($request),
            status: 201,
        );
    }
}
