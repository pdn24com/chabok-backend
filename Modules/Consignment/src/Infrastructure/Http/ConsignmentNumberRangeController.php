<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Consignment\Application\ConsignmentNumberRangeService;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConsignmentNumberRangeController
{
    public function __construct(private ConsignmentNumberRangeService $ranges) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:AVAILABLE,EXHAUSTED,DISABLED'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        return ApiResponder::paginated($request, $this->ranges->ranges($this->actor($request), $filters), fn ($row) => $this->ranges->resource($row));
    }

    public function validateRange(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['numeric_prefix', 'total_length', 'serial_start', 'serial_end']);
        return ApiResponder::success($request, $this->ranges->validate($this->actor($request), $this->definition($request)));
    }

    public function store(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['title', 'numeric_prefix', 'total_length', 'serial_start', 'serial_end']);
        $input = ['title' => $request->validate(['title' => ['required', 'string', 'max:200']])['title'], ...$this->definition($request)];
        return ApiResponder::success($request, $this->ranges->create($this->actor($request), $input, $this->correlation($request)), status: 201);
    }

    public function show(Request $request, string $rangeId): JsonResponse
    {
        return ApiResponder::success($request, $this->ranges->range($this->actor($request), $rangeId));
    }

    public function allocations(Request $request, string $rangeId): JsonResponse
    {
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'page_size' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        return ApiResponder::paginated($request, $this->ranges->allocations($this->actor($request), $rangeId, $filters), fn ($row) => $this->ranges->allocationResource($row));
    }

    public function disable(Request $request, string $rangeId): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        return ApiResponder::success($request, $this->ranges->disable($this->actor($request), $rangeId, $this->correlation($request)));
    }

    /** @return array<string,mixed> */
    private function definition(Request $request): array
    {
        return $request->validate([
            'numeric_prefix' => ['required', 'string', 'max:100'],
            'total_length' => ['required'],
            'serial_start' => ['required', 'string', 'max:100'],
            'serial_end' => ['required', 'string', 'max:100'],
        ]);
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
