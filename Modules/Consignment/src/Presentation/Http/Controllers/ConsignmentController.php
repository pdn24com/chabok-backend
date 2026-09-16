<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConsignmentController
{
    public function __construct(
        private \Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsHandler $list,
        private \Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsHandler $statusGroupCounts,
        private \Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsHandler $filterOptions,
        private \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler $get,
        private \Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentHandler $create,
        private \Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentHandler $edit,
        private \Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteHandler $calculate,
        private \Modules\Consignment\Application\Services\ConsignmentProjection $projection,
    )
    {
    }

    public function index(\Modules\Consignment\Presentation\Http\Requests\ListConsignmentsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $paginator = $this->list->handle(new \Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsCommand($this->principal($request), $this->nodeId($request), $filters))->data;
        $statusCounts = $this->statusGroupCounts->handle(new \Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsCommand($this->principal($request), $this->nodeId($request), $filters))->data;
        return ApiResponder::paginated($request, $paginator, fn($row): array => $this->projection->listItem((array) $row), [
            'status_counts' => $statusCounts,
            'filter_options' => $this->filterOptions->handle(new \Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsCommand($this->principal($request), $this->nodeId($request)))->data,
        ]);
    }

    public function quote(\Modules\Consignment\Presentation\Http\Requests\CalculateConsignmentQuoteRequest $request): JsonResponse
    {
        $input = $request->validated();
        $purpose = (string) $input['purpose'];
        $consignmentId = isset($input['consignment_id']) ? (string) $input['consignment_id'] : null;
        $expectedVersion = isset($input['expected_version']) ? (int) $input['expected_version'] : null;
        unset($input['purpose'], $input['consignment_id'], $input['expected_version']);
        if ($purpose === 'CREATE') {
            $input = $request->normalizePilotCreate($input);
        }
        return ApiResponder::success($request, $this->calculate->handle(new \Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteCommand($this->principal($request), $this->nodeId($request), $purpose, $input, $consignmentId, $expectedVersion))->data);
    }

    public function store(\Modules\Consignment\Presentation\Http\Requests\CreateConsignmentRequest $request): JsonResponse
    {
        $input = $request->validated();
        $input = $request->normalizePilotCreate($input);
        return ApiResponder::success($request, $this->create->handle(new \Modules\Consignment\Application\UseCases\CreateConsignment\CreateConsignmentCommand($this->principal($request), $this->nodeId($request), $input, $this->correlationId($request)))->data, status: 201);
    }

    public function show(Request $request, string $consignmentId): JsonResponse
    {
        return ApiResponder::success($request, $this->get->handle(new \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand($this->principal($request), $this->nodeId($request), $consignmentId))->data);
    }

    public function update(
        \Modules\Consignment\Presentation\Http\Requests\EditConsignmentRequest $request,
        string $consignmentId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->edit->handle(new \Modules\Consignment\Application\UseCases\EditConsignment\EditConsignmentCommand($this->principal($request), $this->nodeId($request), $consignmentId, $input, $this->correlationId($request)))->data);
    }
    /** @return array<string, list<string>> */
    /** @return array<string, list<string>> */
    /** @return array<string, list<string>> */
    /** @param array<string,mixed> $input @return array<string,mixed> */

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function nodeId(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (!is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.', ['X-Node-Id' => ['The operational node header is required.']]);
        }
        return $nodeId;
    }

    private function correlationId(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
