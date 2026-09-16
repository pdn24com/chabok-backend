<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConsignmentNumberRangeController
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeProjection $projection,
        private \Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesHandler $ranges,
        private \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler $range,
        private \Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeHandler $validate,
        private \Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeHandler $create,
        private \Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeHandler $disable,
        private \Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsHandler $allocations,
    )
    {
    }

    public function index(\Modules\Consignment\Presentation\Http\Requests\ListNumberRangesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::paginated($request, $this->ranges->handle(new \Modules\Consignment\Application\UseCases\ListNumberRanges\ListNumberRangesCommand($this->actor($request), $filters))->data, fn($row) => $this->projection->resource($row));
    }

    public function validateRange(\Modules\Consignment\Presentation\Http\Requests\ValidateNumberRangeRequest $request): JsonResponse
    {
        return ApiResponder::success($request, $this->validate->handle(new \Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeCommand($this->actor($request), $request->validated()))->data);
    }

    public function store(\Modules\Consignment\Presentation\Http\Requests\CreateNumberRangeRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->create->handle(new \Modules\Consignment\Application\UseCases\CreateNumberRange\CreateNumberRangeCommand($this->actor($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function show(Request $request, string $rangeId): JsonResponse
    {
        return ApiResponder::success($request, $this->range->handle(new \Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand($this->actor($request), $rangeId))->data);
    }

    public function allocations(
        \Modules\Consignment\Presentation\Http\Requests\ListNumberAllocationsRequest $request,
        string $rangeId,
    ): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::paginated($request, $this->allocations->handle(new \Modules\Consignment\Application\UseCases\ListNumberAllocations\ListNumberAllocationsCommand($this->actor($request), $rangeId, $filters))->data, fn($row) => $this->projection->allocationResource($row));
    }

    public function disable(Request $request, string $rangeId): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        return ApiResponder::success($request, $this->disable->handle(new \Modules\Consignment\Application\UseCases\DisableNumberRange\DisableNumberRangeCommand($this->actor($request), $rangeId, $this->correlation($request)))->data);
    }

    private function actor(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
