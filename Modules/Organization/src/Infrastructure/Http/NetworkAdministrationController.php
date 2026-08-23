<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Organization\Application\NetworkAdministrationService;

final readonly class NetworkAdministrationController
{
    public function __construct(private NetworkAdministrationService $network) {}

    public function areas(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:200'], 'status' => ['sometimes', 'nullable', 'in:ACTIVE,INACTIVE'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        return ApiResponder::paginated($request, $this->network->areas($this->actor($request), $filters), fn ($row) => $this->network->areaResource($row));
    }

    public function area(Request $request, string $areaId): JsonResponse
    {
        return ApiResponder::success($request, $this->network->area($this->actor($request), $areaId));
    }

    public function createArea(Request $request): JsonResponse
    {
        $input = $request->validate(['area_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'], 'area_title' => ['required', 'string', 'max:200'], 'parent_area_id' => ['sometimes', 'nullable', 'uuid']]);
        return ApiResponder::success($request, $this->network->createArea($this->actor($request), $input, $this->correlation($request)), status: 201);
    }

    public function updateArea(Request $request, string $areaId): JsonResponse
    {
        $input = $request->validate(['area_title' => ['sometimes', 'string', 'max:200'], 'parent_area_id' => ['sometimes', 'nullable', 'uuid'], 'status' => ['sometimes', 'in:ACTIVE,INACTIVE'], 'expected_version' => ['required', 'integer', 'min:1']]);
        if (count($input) === 1) throw \Illuminate\Validation\ValidationException::withMessages(['request' => ['At least one mutable field is required.']]);
        return ApiResponder::success($request, $this->network->updateArea($this->actor($request), $areaId, $input, $this->correlation($request)));
    }

    public function nodes(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:200'], 'status' => ['sometimes', 'nullable', 'in:ACTIVE,INACTIVE'], 'area_id' => ['sometimes', 'nullable', 'uuid'], 'node_type' => ['sometimes', 'nullable', 'in:BRANCH,HUB,GATEWAY'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        return ApiResponder::paginated($request, $this->network->nodes($this->actor($request), $filters), fn ($row) => $this->network->nodeResource($row));
    }

    public function node(Request $request, string $nodeId): JsonResponse
    {
        return ApiResponder::success($request, $this->network->node($this->actor($request), $nodeId));
    }

    public function createNode(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->network->createNode($this->actor($request), $this->nodeInput($request, true), $this->correlation($request)), status: 201);
    }

    public function updateNode(Request $request, string $nodeId): JsonResponse
    {
        return ApiResponder::success($request, $this->network->updateNode($this->actor($request), $nodeId, $this->nodeInput($request, false), $this->correlation($request)));
    }

    /** @return array<string, mixed> */
    private function nodeInput(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $input = $request->validate([
            'area_id' => [$required, 'uuid'], 'node_code' => [$creating ? 'required' : 'prohibited', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'node_title' => [$required, 'string', 'max:200'], 'node_type' => [$required, 'in:BRANCH,HUB,GATEWAY'],
            'capabilities' => [$required, 'array'], 'capabilities.*' => ['string', 'distinct', 'in:PICKUP,CONSOLIDATION,GATEWAY,LINEHAUL,DELIVERY,CUSTOMER_HANDOFF'],
            'address' => [$required, 'array'], 'address.country_code' => [$required, 'in:IR'], 'address.province_id' => ['sometimes', 'nullable', 'uuid'], 'address.city_id' => ['sometimes', 'nullable', 'uuid'],
            'address.postal_code' => ['sometimes', 'nullable', 'regex:/^[0-9]{10}$/'], 'address.line' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'address.location' => ['sometimes', 'nullable', 'array'], 'address.location.latitude' => ['required_with:address.location', 'numeric', 'between:-90,90'], 'address.location.longitude' => ['required_with:address.location', 'numeric', 'between:-180,180'],
            'status' => [$creating ? 'prohibited' : 'sometimes', 'in:ACTIVE,INACTIVE'], 'expected_version' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
        ]);
        if (! $creating && count($input) === 1) throw \Illuminate\Validation\ValidationException::withMessages(['request' => ['At least one mutable field is required.']]);
        return $input;
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
