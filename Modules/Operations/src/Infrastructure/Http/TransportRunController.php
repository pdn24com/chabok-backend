<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Operations\Application\TransportRunService;

final readonly class TransportRunController
{
    public function __construct(private TransportRunService $runs) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'in:CREATED,LOADED,DEPARTED,ARRIVED,CLOSED'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $page = $this->runs->list($this->principal($request), $this->node($request), $filters);
        return ApiResponder::success($request, $page->items(), $this->pagination($page));
    }

    public function candidates(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->runs->candidates($this->principal($request), $this->node($request)));
    }

    public function store(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['route_plan_leg_id', 'driver_id', 'vehicle_id']);
        $input = $request->validate(['route_plan_leg_id' => ['required', 'uuid'], 'driver_id' => ['required', 'uuid'], 'vehicle_id' => ['required', 'uuid']]);
        return ApiResponder::success($request, $this->runs->create($this->principal($request), $this->node($request), (string) $input['route_plan_leg_id'], (string) $input['driver_id'], (string) $input['vehicle_id'], $this->correlation($request)), status: 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponder::success($request, $this->runs->get($this->principal($request), $this->node($request), $id));
    }

    public function load(Request $request, string $id): JsonResponse
    {
        StrictPayload::assertOnly($request, ['expected_version', 'parcel_ids']);
        $input = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'parcel_ids' => ['required', 'array', 'min:1'], 'parcel_ids.*' => ['required', 'uuid', 'distinct']]);
        return ApiResponder::success($request, $this->runs->load($this->principal($request), $this->node($request), $id, $input['parcel_ids'], (int) $input['expected_version'], $this->correlation($request)));
    }

    public function depart(Request $request, string $id): JsonResponse
    {
        return ApiResponder::success($request, $this->runs->depart($this->principal($request), $this->node($request), $id, $this->expected($request), $this->correlation($request)));
    }

    public function arrive(Request $request, string $id): JsonResponse
    {
        return ApiResponder::success($request, $this->runs->arrive($this->principal($request), $this->node($request), $id, $this->expected($request), $this->correlation($request)));
    }

    public function close(Request $request, string $id): JsonResponse
    {
        return ApiResponder::success($request, $this->runs->close($this->principal($request), $this->node($request), $id, $this->expected($request), $this->correlation($request)));
    }

    private function expected(Request $request): int
    {
        StrictPayload::assertOnly($request, ['expected_version']);
        return (int) $request->validate(['expected_version' => ['required', 'integer', 'min:1']])['expected_version'];
    }

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function node(Request $request): string
    {
        $id = $request->attributes->get('node_id');
        if (! is_string($id) || $id === '') throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational Node is required.');
        return $id;
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }

    /** @return array<string,mixed> */
    private function pagination(LengthAwarePaginator $page): array
    {
        return ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]];
    }
}
