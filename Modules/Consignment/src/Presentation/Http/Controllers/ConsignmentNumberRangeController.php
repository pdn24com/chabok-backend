<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Consignment\Application\Dto\NumberRangeCreationDto;
use Modules\Consignment\Application\Dto\NumberRangeFiltersDto;
use Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeCommand;
use Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeHandler;
use Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeCommand;
use Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeHandler;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler;
use Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsCommand;
use Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsHandler;
use Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesCommand;
use Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesHandler;
use Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeCommand;
use Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeHandler;
use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;
use Modules\Consignment\Presentation\Http\Requests\CreateNumberRangeRequest;
use Modules\Consignment\Presentation\Http\Requests\ListNumberAllocationsRequest;
use Modules\Consignment\Presentation\Http\Requests\ListNumberRangesRequest;
use Modules\Consignment\Presentation\Http\Requests\ValidateNumberRangeRequest;
use Modules\Consignment\Presentation\Http\Resources\NumberAllocationResource;
use Modules\Consignment\Presentation\Http\Resources\NumberRangeResource;
use Modules\Consignment\Presentation\Http\Resources\NumberRangeValidationResource;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ConsignmentNumberRangeController
{
    public function index(ListNumberRangesRequest $request, ListNumberRangesHandler $listNumberRangesHandler): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::paginated($request, $listNumberRangesHandler->handle(new ListNumberRangesCommand($request->attributes->get('principal'), NumberRangeFiltersDto::fromValidated($filters))), fn ($row) => (new NumberRangeResource($row))->resolve());
    }

    public function validateRange(ValidateNumberRangeRequest $request, ValidateNumberRangeHandler $validateNumberRangeHandler): JsonResponse
    {
        return ApiResponder::success($request, (new NumberRangeValidationResource($validateNumberRangeHandler->handle(new ValidateNumberRangeCommand($request->attributes->get('principal'), NumberRangeInput::fromValidated($request->validated())))))->resolve());
    }

    public function store(CreateNumberRangeRequest $request, CreateNumberRangeHandler $createNumberRangeHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new NumberRangeResource($createNumberRangeHandler->handle(new CreateNumberRangeCommand($request->attributes->get('principal'), NumberRangeCreationDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve(), status: 201);
    }

    public function show(Request $request, GetNumberRangeHandler $getNumberRangeHandler, string $rangeId): JsonResponse
    {
        return ApiResponder::success($request, (new NumberRangeResource($getNumberRangeHandler->handle(new GetNumberRangeCommand($request->attributes->get('principal'), $rangeId))))->resolve());
    }

    public function allocations(ListNumberAllocationsRequest $request, ListNumberAllocationsHandler $listNumberAllocationsHandler, string $rangeId): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::paginated($request, $listNumberAllocationsHandler->handle(new ListNumberAllocationsCommand($request->attributes->get('principal'), $rangeId, NumberRangeFiltersDto::fromValidated($filters))), fn ($row) => (new NumberAllocationResource($row))->resolve());
    }

    public function disable(Request $request, DisableNumberRangeHandler $disableNumberRangeHandler, string $rangeId): JsonResponse
    {
        StrictPayload::assertOnly($request, []);

        return ApiResponder::success($request, (new NumberRangeResource($disableNumberRangeHandler->handle(new DisableNumberRangeCommand($request->attributes->get('principal'), $rangeId, (string) $request->attributes->get('correlation_id')))))->resolve());
    }
}
