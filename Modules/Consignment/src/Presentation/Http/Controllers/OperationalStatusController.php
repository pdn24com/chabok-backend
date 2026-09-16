<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatuses\ListOperationalStatusesHandler;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusCommand;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusHandler;
use Modules\Consignment\Presentation\Http\Requests\CreateOperationalStatusRequest;
use Modules\Consignment\Presentation\Http\Requests\UpdateOperationalStatusRequest;
use Modules\Consignment\Presentation\Http\Requests\OperationalStatusRequest;
use Modules\Foundation\Presentation\Http\ApiResponder;

final readonly class OperationalStatusController
{
    public function __construct(private ListOperationalStatusesHandler $list, private SaveOperationalStatusHandler $saveStatus)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->list->handle(new ListOperationalStatusesCommand($request->attributes->get('principal')))->data);
    }

    public function store(CreateOperationalStatusRequest $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(UpdateOperationalStatusRequest $request, string $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    private function save(OperationalStatusRequest $request, ?string $id): JsonResponse
    {
        $result = $this->saveStatus->handle(new SaveOperationalStatusCommand($request->attributes->get('principal'), $id, $request->validated(), (string) $request->attributes->get('correlation_id')));
        return ApiResponder::success($request, $result->data, status: $id ? 200 : 201);
    }
}
