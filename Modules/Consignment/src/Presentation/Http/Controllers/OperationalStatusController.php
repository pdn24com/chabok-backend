<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Consignment\Application\Dto\OperationalStatusDto;
use Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesHandler;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusCommand;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusHandler;
use Modules\Consignment\Presentation\Http\Requests\CreateOperationalStatusRequest;
use Modules\Consignment\Presentation\Http\Requests\OperationalStatusRequest;
use Modules\Consignment\Presentation\Http\Requests\UpdateOperationalStatusRequest;
use Modules\Consignment\Presentation\Http\Resources\OperationalStatusResource;
use Modules\Foundation\Presentation\Http\ApiResponder;

final class OperationalStatusController
{
    public function index(Request $request, ListOperationalStatusesHandler $listOperationalStatusesHandler): JsonResponse
    {
        return ApiResponder::success($request, OperationalStatusResource::collection($listOperationalStatusesHandler->handle(new ListOperationalStatusesCommand($request->attributes->get('principal'))))->resolve($request));
    }

    public function store(CreateOperationalStatusRequest $request, SaveOperationalStatusHandler $saveOperationalStatusHandler): JsonResponse
    {
        return $this->save($request, $saveOperationalStatusHandler, null);
    }

    public function update(UpdateOperationalStatusRequest $request, SaveOperationalStatusHandler $saveOperationalStatusHandler, string $id): JsonResponse
    {
        return $this->save($request, $saveOperationalStatusHandler, $id);
    }

    private function save(OperationalStatusRequest $request, SaveOperationalStatusHandler $saveOperationalStatusHandler, ?string $id): JsonResponse
    {
        $result = $saveOperationalStatusHandler->handle(new SaveOperationalStatusCommand($request->attributes->get('principal'), $id, OperationalStatusDto::fromValidated($request->validated()), (string) $request->attributes->get('correlation_id')));

        return ApiResponder::success($request, (new OperationalStatusResource($result))->resolve($request), status: $id ? 200 : 201);
    }
}
