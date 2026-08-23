<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Operations\Application\CoveragePolicyService;
use Modules\Operations\Application\RouteDefinitionService;

final readonly class NetworkConfigurationController
{
    public function __construct(private CoveragePolicyService $coverage, private RouteDefinitionService $routes) {}

    public function coverageIndex(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'target' => ['nullable', Rule::in(['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE'])], 'page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']]);
        $page = $this->coverage->list($this->principal($request), $filters);
        return ApiResponder::paginated($request, $page, fn ($row): array => ['coverage_policy_id' => (string) $row->coverage_policy_id, 'policy_code' => (string) $row->policy_code, 'policy_title' => (string) $row->policy_title, 'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null]);
    }

    public function coverageStore(Request $request): JsonResponse
    {
        $input = $request->validate(['policy_code' => ['required', 'string', 'max:80'], 'policy_title' => ['required', 'string', 'max:200']]);
        return ApiResponder::success($request, $this->coverage->create($this->principal($request), $input, $this->correlation($request)), status: 201);
    }

    public function coverageShow(Request $request, string $policyId): JsonResponse { return ApiResponder::success($request, $this->coverage->policy($this->principal($request), $policyId)); }

    public function coverageVersionStore(Request $request, string $policyId): JsonResponse
    {
        $input = $request->validate($this->coverageVersionRules(false));
        return ApiResponder::success($request, $this->coverage->createVersion($this->principal($request), $policyId, $input, $this->correlation($request)), status: 201);
    }

    public function coverageVersionIndex(Request $request, string $policyId): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']]);
        $page = $this->coverage->history($this->principal($request), $policyId, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20));
        return ApiResponder::paginated($request, $page, fn ($row): array => $this->coverage->presentVersion($row));
    }

    public function coverageVersionShow(Request $request, string $policyId, string $versionId): JsonResponse { return ApiResponder::success($request, $this->coverage->version($this->principal($request), $policyId, $versionId)); }
    public function coverageVersionUpdate(Request $request, string $policyId, string $versionId): JsonResponse { $input = $request->validate($this->coverageVersionRules(true)); return ApiResponder::success($request, $this->coverage->update($this->principal($request), $policyId, $versionId, $input, $this->correlation($request))); }
    public function coverageTransition(Request $request, string $policyId, string $versionId, string $action): JsonResponse { $input = $request->validate($this->lifecycleRules()); return ApiResponder::success($request, $this->coverage->transition($this->principal($request), $policyId, $versionId, $action, (int) $input['expected_version'], $input['note'] ?? null, $this->correlation($request))); }

    public function routeIndex(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'purpose' => ['nullable', Rule::in(['TRUNK', 'LAST_MILE'])], 'page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']]);
        $page = $this->routes->list($this->principal($request), $filters);
        return ApiResponder::paginated($request, $page, fn ($row): array => ['route_definition_id' => (string) $row->route_definition_id, 'route_code' => (string) $row->route_code, 'route_title' => (string) $row->route_title, 'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null]);
    }

    public function routeStore(Request $request): JsonResponse
    {
        $input = $request->validate(['route_code' => ['required', 'string', 'max:80'], 'route_title' => ['required', 'string', 'max:200']]);
        return ApiResponder::success($request, $this->routes->create($this->principal($request), $input, $this->correlation($request)), status: 201);
    }

    public function routeShow(Request $request, string $definitionId): JsonResponse { return ApiResponder::success($request, $this->routes->definition($this->principal($request), $definitionId)); }
    public function routeVersionStore(Request $request, string $definitionId): JsonResponse { $input = $request->validate($this->routeVersionRules(false)); return ApiResponder::success($request, $this->routes->createVersion($this->principal($request), $definitionId, $input, $this->correlation($request)), status: 201); }
    public function routeVersionIndex(Request $request, string $definitionId): JsonResponse { $filters = $request->validate(['page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']]); $page = $this->routes->history($this->principal($request), $definitionId, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20)); return ApiResponder::paginated($request, $page, fn ($row): array => $this->routes->presentVersion($row)); }
    public function routeVersionShow(Request $request, string $definitionId, string $versionId): JsonResponse { return ApiResponder::success($request, $this->routes->version($this->principal($request), $definitionId, $versionId)); }
    public function routeVersionUpdate(Request $request, string $definitionId, string $versionId): JsonResponse { $input = $request->validate($this->routeVersionRules(true)); return ApiResponder::success($request, $this->routes->update($this->principal($request), $definitionId, $versionId, $input, $this->correlation($request))); }
    public function routeTransition(Request $request, string $definitionId, string $versionId, string $action): JsonResponse { $input = $request->validate($this->lifecycleRules()); return ApiResponder::success($request, $this->routes->transition($this->principal($request), $definitionId, $versionId, $action, (int) $input['expected_version'], $input['note'] ?? null, $this->correlation($request))); }

    /** @return array<string,list<mixed>> */
    private function coverageVersionRules(bool $update): array
    {
        return [
            'source_version_id' => [$update ? 'prohibited' : 'nullable', 'uuid'],
            'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'expected_version' => [$update ? 'required' : 'prohibited', 'integer', 'min:1'],
            'rules' => [$update ? 'sometimes' : 'required', 'array', 'min:1'],
            'rules.*.target' => ['required', Rule::in(['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE'])],
            'rules.*.target_node_id' => ['required', 'uuid'], 'rules.*.priority' => ['required', 'integer', 'between:-100000,100000'],
            'rules.*.offering_version_id' => ['nullable', 'uuid'], 'rules.*.criterion' => ['required', 'array'],
            'rules.*.criterion.criterion_type' => ['required', Rule::in(['PROVINCE', 'CITY', 'POSTAL_RANGE', 'POLYGON', 'POINT_RADIUS'])],
            'rules.*.criterion.province_id' => ['nullable', 'uuid'], 'rules.*.criterion.city_id' => ['nullable', 'uuid'],
            'rules.*.criterion.postal_code_from' => ['nullable', 'regex:/^\d{10}$/'], 'rules.*.criterion.postal_code_to' => ['nullable', 'regex:/^\d{10}$/'],
            'rules.*.criterion.geometry' => ['nullable', 'array'], 'rules.*.criterion.center' => ['nullable', 'array'],
            'rules.*.criterion.center.latitude' => ['nullable', 'numeric', 'between:-90,90'], 'rules.*.criterion.center.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rules.*.criterion.radius_meters' => ['nullable', 'integer', 'between:1,500000'],
        ];
    }

    /** @return array<string,list<mixed>> */
    private function routeVersionRules(bool $update): array
    {
        return [
            'source_version_id' => [$update ? 'prohibited' : 'nullable', 'uuid'],
            'purpose' => [$update ? 'prohibited' : 'required', Rule::in(['TRUNK', 'LAST_MILE'])],
            'origin_node_id' => [$update ? 'prohibited' : 'required', 'uuid'], 'destination_node_id' => [$update ? 'prohibited' : 'required', 'uuid'],
            'priority' => [$update ? 'sometimes' : 'required', 'integer', 'between:-100000,100000'], 'offering_version_id' => ['nullable', 'uuid'],
            'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'expected_version' => [$update ? 'required' : 'prohibited', 'integer', 'min:1'],
            'legs' => [$update ? 'sometimes' : 'required', 'array', 'min:1'], 'legs.*.leg_order' => ['required', 'integer', 'min:1'],
            'legs.*.origin_node_id' => ['required', 'uuid'], 'legs.*.destination_node_id' => ['required', 'uuid'],
        ];
    }

    /** @return array<string,list<mixed>> */
    private function lifecycleRules(): array { return ['expected_version' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:500']]; }
    private function principal(Request $request): AuthenticatedPrincipal { /** @var AuthenticatedPrincipal */ return $request->attributes->get('principal'); }
    private function correlation(Request $request): string { return (string) $request->attributes->get('correlation_id'); }
}
