<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Consignment\Application\Dto\ConsignmentFiltersDto;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteCommand;
use Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteHandler;
use Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsCommand;
use Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsHandler;
use Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentCommand;
use Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentHandler;
use Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentCommand;
use Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsCommand;
use Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsHandler;
use Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsCommand;
use Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsHandler;
use Modules\Consignment\Presentation\Http\Requests\CalculateConsignmentQuoteRequest;
use Modules\Consignment\Presentation\Http\Requests\CreateConsignmentRequest;
use Modules\Consignment\Presentation\Http\Requests\EditConsignmentRequest;
use Modules\Consignment\Presentation\Http\Requests\ListConsignmentsRequest;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentDetailResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentFilterOptionsResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentListResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentQuoteResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentStatusCountsResource;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;

final class ConsignmentController
{
    public function index(ListConsignmentsRequest $request, CountConsignmentStatusGroupsHandler $countConsignmentStatusGroupsHandler, GetConsignmentFilterOptionsHandler $getConsignmentFilterOptionsHandler, ListConsignmentsHandler $listConsignmentsHandler): JsonResponse
    {
        $filters = $request->validated();
        $paginator = $listConsignmentsHandler->handle(new ListConsignmentsCommand($request->attributes->get('principal'), $this->nodeId($request), ConsignmentFiltersDto::fromValidated($filters)));
        $statusCounts = (new ConsignmentStatusCountsResource($countConsignmentStatusGroupsHandler->handle(new CountConsignmentStatusGroupsCommand($request->attributes->get('principal'), $this->nodeId($request), ConsignmentFiltersDto::fromValidated($filters)))))->resolve();

        return ApiResponder::paginated($request, $paginator, fn ($row): array => (new ConsignmentListResource($row))->resolve(), [
            'status_counts' => $statusCounts,
            'filter_options' => (new ConsignmentFilterOptionsResource($getConsignmentFilterOptionsHandler->handle(new GetConsignmentFilterOptionsCommand($request->attributes->get('principal'), $this->nodeId($request)))))->resolve(),
        ]);
    }

    public function quote(CalculateConsignmentQuoteRequest $request, CalculateConsignmentQuoteHandler $calculateConsignmentQuoteHandler): JsonResponse
    {
        $input = $request->validated();
        $purpose = (string) $input['purpose'];
        $consignmentId = isset($input['consignment_id']) ? (string) $input['consignment_id'] : null;
        $expectedVersion = isset($input['expected_version']) ? (int) $input['expected_version'] : null;
        unset($input['purpose'], $input['consignment_id'], $input['expected_version']);
        if ($purpose === 'CREATE') {
            $input = $request->normalizePilotCreate($input);
        }

        return ApiResponder::success($request, (new ConsignmentQuoteResource($calculateConsignmentQuoteHandler->handle(new CalculateConsignmentQuoteCommand($request->attributes->get('principal'), $this->nodeId($request), $purpose, ConsignmentInputMapper::draft($input), $consignmentId, $expectedVersion))))->resolve());
    }

    public function store(CreateConsignmentRequest $request, CreateConsignmentHandler $createConsignmentHandler): JsonResponse
    {
        $input = $request->validated();
        $input = $request->normalizePilotCreate($input);

        return ApiResponder::success($request, (new ConsignmentDetailResource($createConsignmentHandler->handle(new CreateConsignmentCommand($request->attributes->get('principal'), $this->nodeId($request), ConsignmentInputMapper::creation($input), (string) $request->attributes->get('correlation_id')))))->resolve(), status: 201);
    }

    public function show(Request $request, GetConsignmentHandler $getConsignmentHandler, string $consignmentId): JsonResponse
    {
        return ApiResponder::success($request, (new ConsignmentDetailResource($getConsignmentHandler->handle(new GetConsignmentCommand($request->attributes->get('principal'), $this->nodeId($request), $consignmentId))))->resolve());
    }

    public function update(EditConsignmentRequest $request, EditConsignmentHandler $editConsignmentHandler, string $consignmentId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ConsignmentDetailResource($editConsignmentHandler->handle(new EditConsignmentCommand($request->attributes->get('principal'), $this->nodeId($request), $consignmentId, ConsignmentInputMapper::edit($input), (string) $request->attributes->get('correlation_id')))))->resolve());
    }

    private function nodeId(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required', ['X-Node-Id' => ['common.operational_node_header_is_required']]);
        }

        return $nodeId;
    }
}
