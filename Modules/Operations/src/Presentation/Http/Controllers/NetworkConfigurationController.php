<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class NetworkConfigurationController
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesHandler $coveragelist,
        private \Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyHandler $coveragecreate,
        private \Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyHandler $coveragepolicy,
        private \Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsHandler $coveragehistory,
        private \Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionHandler $coveragecreateVersion,
        private \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler $coverageversion,
        private \Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionHandler $coverageupdate,
        private \Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionHandler $coveragetransition,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coverageReader,
        private \Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsHandler $routeslist,
        private \Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionHandler $routescreate,
        private \Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionHandler $routesdefinition,
        private \Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsHandler $routeshistory,
        private \Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionHandler $routescreateVersion,
        private \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler $routesversion,
        private \Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionHandler $routesupdate,
        private \Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionHandler $routestransition,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routesReader,
    )
    {
    }

    public function coverageIndex(\Modules\Operations\Presentation\Http\Requests\CoverageIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->coveragelist->handle(new \Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesCommand($this->principal($request), $filters))->data;
        return ApiResponder::paginated($request, $page, fn($row): array => [
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'policy_code' => (string) $row->policy_code,
            'policy_title' => (string) $row->policy_title,
            'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null,
        ]);
    }

    public function coverageStore(\Modules\Operations\Presentation\Http\Requests\CoverageStoreRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->coveragecreate->handle(new \Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function coverageShow(Request $request, string $policyId): JsonResponse
    {
        return ApiResponder::success($request, $this->coveragepolicy->handle(new \Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyCommand($this->principal($request), $policyId))->data);
    }

    public function coverageVersionStore(
        \Modules\Operations\Presentation\Http\Requests\CoverageVersionStoreRequest $request,
        string $policyId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->coveragecreateVersion->handle(new \Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionCommand($this->principal($request), $policyId, $input, $this->correlation($request)))->data, status: 201);
    }

    public function coverageVersionIndex(
        \Modules\Operations\Presentation\Http\Requests\CoverageVersionIndexRequest $request,
        string $policyId,
    ): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->coveragehistory->handle(new \Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsCommand($this->principal($request), $policyId, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20)))->data;
        return ApiResponder::paginated($request, $page, fn($row): array => $this->coverageReader->presentVersion($row));
    }

    public function coverageVersionShow(Request $request, string $policyId, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->coverageversion->handle(new \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand($this->principal($request), $policyId, $versionId))->data);
    }

    public function coverageVersionUpdate(
        \Modules\Operations\Presentation\Http\Requests\CoverageVersionUpdateRequest $request,
        string $policyId,
        string $versionId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->coverageupdate->handle(new \Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionCommand($this->principal($request), $policyId, $versionId, $input, $this->correlation($request)))->data);
    }

    public function coverageTransition(
        \Modules\Operations\Presentation\Http\Requests\CoverageTransitionRequest $request,
        string $policyId,
        string $versionId,
        string $action,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->coveragetransition->handle(new \Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionCommand($this->principal($request), $policyId, $versionId, $action, (int) $input['expected_version'], $input['note'] ?? null, $this->correlation($request)))->data);
    }

    public function routeIndex(\Modules\Operations\Presentation\Http\Requests\RouteIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->routeslist->handle(new \Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsCommand($this->principal($request), $filters))->data;
        return ApiResponder::paginated($request, $page, fn($row): array => [
            'route_definition_id' => (string) $row->route_definition_id,
            'route_code' => (string) $row->route_code,
            'route_title' => (string) $row->route_title,
            'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null,
        ]);
    }

    public function routeStore(\Modules\Operations\Presentation\Http\Requests\RouteStoreRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->routescreate->handle(new \Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function routeShow(Request $request, string $definitionId): JsonResponse
    {
        return ApiResponder::success($request, $this->routesdefinition->handle(new \Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionCommand($this->principal($request), $definitionId))->data);
    }

    public function routeVersionStore(
        \Modules\Operations\Presentation\Http\Requests\RouteVersionStoreRequest $request,
        string $definitionId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->routescreateVersion->handle(new \Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionCommand($this->principal($request), $definitionId, $input, $this->correlation($request)))->data, status: 201);
    }

    public function routeVersionIndex(
        \Modules\Operations\Presentation\Http\Requests\RouteVersionIndexRequest $request,
        string $definitionId,
    ): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->routeshistory->handle(new \Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsCommand($this->principal($request), $definitionId, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20)))->data;
        return ApiResponder::paginated($request, $page, fn($row): array => $this->routesReader->presentVersion($row));
    }

    public function routeVersionShow(Request $request, string $definitionId, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->routesversion->handle(new \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand($this->principal($request), $definitionId, $versionId))->data);
    }

    public function routeVersionUpdate(
        \Modules\Operations\Presentation\Http\Requests\RouteVersionUpdateRequest $request,
        string $definitionId,
        string $versionId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->routesupdate->handle(new \Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionCommand($this->principal($request), $definitionId, $versionId, $input, $this->correlation($request)))->data);
    }

    public function routeTransition(
        \Modules\Operations\Presentation\Http\Requests\RouteTransitionRequest $request,
        string $definitionId,
        string $versionId,
        string $action,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->routestransition->handle(new \Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionCommand($this->principal($request), $definitionId, $versionId, $action, (int) $input['expected_version'], $input['note'] ?? null, $this->correlation($request)))->data);
    }
    /** @return array<string,list<mixed>> */

    private function principal(Request $request): AuthenticatedPrincipal
    {
        /** @var AuthenticatedPrincipal */
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
