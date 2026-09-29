<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Operations\Application\Dto\CoveragePolicyDto;
use Modules\Operations\Application\Dto\CoveragePolicyFiltersDto;
use Modules\Operations\Application\Dto\CoverageVersionChangesDto;
use Modules\Operations\Application\Dto\CoverageVersionDto;
use Modules\Operations\Application\Dto\RouteDefinitionDto;
use Modules\Operations\Application\Dto\RouteDefinitionFiltersDto;
use Modules\Operations\Application\Dto\RouteVersionChangesDto;
use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionCommand;
use Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionHandler;
use Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionHandler;
use Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionCommand;
use Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionHandler;
use Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler;
use Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionHandler;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler;
use Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesCommand;
use Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesHandler;
use Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsCommand;
use Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsHandler;
use Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsCommand;
use Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsHandler;
use Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsCommand;
use Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsHandler;
use Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionCommand;
use Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionHandler;
use Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionCommand;
use Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionHandler;
use Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionCommand;
use Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionHandler;
use Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionCommand;
use Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionHandler;
use Modules\Operations\Presentation\Http\Requests\CoverageIndexRequest;
use Modules\Operations\Presentation\Http\Requests\CoverageStoreRequest;
use Modules\Operations\Presentation\Http\Requests\CoverageTransitionRequest;
use Modules\Operations\Presentation\Http\Requests\CoverageVersionIndexRequest;
use Modules\Operations\Presentation\Http\Requests\CoverageVersionStoreRequest;
use Modules\Operations\Presentation\Http\Requests\CoverageVersionUpdateRequest;
use Modules\Operations\Presentation\Http\Requests\RouteIndexRequest;
use Modules\Operations\Presentation\Http\Requests\RouteStoreRequest;
use Modules\Operations\Presentation\Http\Requests\RouteTransitionRequest;
use Modules\Operations\Presentation\Http\Requests\RouteVersionIndexRequest;
use Modules\Operations\Presentation\Http\Requests\RouteVersionStoreRequest;
use Modules\Operations\Presentation\Http\Requests\RouteVersionUpdateRequest;
use Modules\Operations\Presentation\Http\Resources\CoveragePolicyResource;
use Modules\Operations\Presentation\Http\Resources\CoverageVersionResource;
use Modules\Operations\Presentation\Http\Resources\RouteDefinitionResource;
use Modules\Operations\Presentation\Http\Resources\RouteVersionResource;

final class NetworkConfigurationController
{
    public function coverageIndex(CoverageIndexRequest $request, ListCoveragePoliciesHandler $listCoveragePoliciesHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listCoveragePoliciesHandler->handle(new ListCoveragePoliciesCommand($request->attributes->get('principal'), CoveragePolicyFiltersDto::fromValidated($filters)));

        return ApiResponder::paginated($request, $page, fn ($row): array => (new CoveragePolicyResource($row))->resolve($request));
    }

    public function coverageStore(CoverageStoreRequest $request, CreateCoveragePolicyHandler $createCoveragePolicyHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new CoveragePolicyResource($createCoveragePolicyHandler->handle(new CreateCoveragePolicyCommand($request->attributes->get('principal'), CoveragePolicyDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function coverageShow(Request $request, GetCoveragePolicyHandler $getCoveragePolicyHandler, string $policyId): JsonResponse
    {
        return ApiResponder::success($request, (new CoveragePolicyResource($getCoveragePolicyHandler->handle(new GetCoveragePolicyCommand($request->attributes->get('principal'), $policyId))))->resolve($request));
    }

    public function coverageVersionStore(CoverageVersionStoreRequest $request, CreateCoverageVersionHandler $createCoverageVersionHandler, string $policyId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new CoverageVersionResource($createCoverageVersionHandler->handle(new CreateCoverageVersionCommand($request->attributes->get('principal'), $policyId, CoverageVersionDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function coverageVersionIndex(CoverageVersionIndexRequest $request, ListCoverageVersionsHandler $listCoverageVersionsHandler, string $policyId): JsonResponse
    {
        $filters = $request->validated();
        $page = $listCoverageVersionsHandler->handle(new ListCoverageVersionsCommand($request->attributes->get('principal'), $policyId, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20)));

        return ApiResponder::paginated($request, $page, fn ($row): array => (new CoverageVersionResource($row))->resolve($request));
    }

    public function coverageVersionShow(
        Request $request, GetCoverageVersionHandler $getCoverageVersionHandler,
        string $policyId,
        string $versionId,
    ): JsonResponse {
        return ApiResponder::success($request, (new CoverageVersionResource($getCoverageVersionHandler->handle(new GetCoverageVersionCommand($request->attributes->get('principal'), $policyId, $versionId))))->resolve($request));
    }

    public function coverageVersionUpdate(
        CoverageVersionUpdateRequest $request, UpdateCoverageVersionHandler $updateCoverageVersionHandler,
        string $policyId,
        string $versionId,
    ): JsonResponse {
        $input = $request->validated();

        return ApiResponder::success($request, (new CoverageVersionResource($updateCoverageVersionHandler->handle(new UpdateCoverageVersionCommand($request->attributes->get('principal'), $policyId, $versionId, CoverageVersionChangesDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function coverageTransition(
        CoverageTransitionRequest $request, TransitionCoverageVersionHandler $transitionCoverageVersionHandler,
        string $policyId,
        string $versionId,
        string $action,
    ): JsonResponse {
        $input = $request->validated();

        return ApiResponder::success($request, (new CoverageVersionResource($transitionCoverageVersionHandler->handle(new TransitionCoverageVersionCommand($request->attributes->get('principal'), $policyId, $versionId, $action, (int) $input['expected_version'], $input['note'] ?? null, (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function routeIndex(RouteIndexRequest $request, ListRouteDefinitionsHandler $listRouteDefinitionsHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listRouteDefinitionsHandler->handle(new ListRouteDefinitionsCommand($request->attributes->get('principal'), RouteDefinitionFiltersDto::fromValidated($filters)));

        return ApiResponder::paginated($request, $page, fn ($row): array => (new RouteDefinitionResource($row))->resolve($request));
    }

    public function routeStore(RouteStoreRequest $request, CreateRouteDefinitionHandler $createRouteDefinitionHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new RouteDefinitionResource($createRouteDefinitionHandler->handle(new CreateRouteDefinitionCommand($request->attributes->get('principal'), RouteDefinitionDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function routeShow(Request $request, GetRouteDefinitionHandler $getRouteDefinitionHandler, string $definitionId): JsonResponse
    {
        return ApiResponder::success($request, (new RouteDefinitionResource($getRouteDefinitionHandler->handle(new GetRouteDefinitionCommand($request->attributes->get('principal'), $definitionId))))->resolve($request));
    }

    public function routeVersionStore(RouteVersionStoreRequest $request, CreateRouteVersionHandler $createRouteVersionHandler, string $definitionId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new RouteVersionResource($createRouteVersionHandler->handle(new CreateRouteVersionCommand($request->attributes->get('principal'), $definitionId, RouteVersionDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function routeVersionIndex(RouteVersionIndexRequest $request, ListRouteVersionsHandler $listRouteVersionsHandler, string $definitionId): JsonResponse
    {
        $filters = $request->validated();
        $page = $listRouteVersionsHandler->handle(new ListRouteVersionsCommand($request->attributes->get('principal'), $definitionId, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20)));

        return ApiResponder::paginated($request, $page, fn ($row): array => (new RouteVersionResource($row))->resolve($request));
    }

    public function routeVersionShow(
        Request $request, GetRouteVersionHandler $getRouteVersionHandler,
        string $definitionId,
        string $versionId,
    ): JsonResponse {
        return ApiResponder::success($request, (new RouteVersionResource($getRouteVersionHandler->handle(new GetRouteVersionCommand($request->attributes->get('principal'), $definitionId, $versionId))))->resolve($request));
    }

    public function routeVersionUpdate(
        RouteVersionUpdateRequest $request, UpdateRouteVersionHandler $updateRouteVersionHandler,
        string $definitionId,
        string $versionId,
    ): JsonResponse {
        $input = $request->validated();

        return ApiResponder::success($request, (new RouteVersionResource($updateRouteVersionHandler->handle(new UpdateRouteVersionCommand($request->attributes->get('principal'), $definitionId, $versionId, RouteVersionChangesDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function routeTransition(
        RouteTransitionRequest $request, TransitionRouteVersionHandler $transitionRouteVersionHandler,
        string $definitionId,
        string $versionId,
        string $action,
    ): JsonResponse {
        $input = $request->validated();

        return ApiResponder::success($request, (new RouteVersionResource($transitionRouteVersionHandler->handle(new TransitionRouteVersionCommand($request->attributes->get('principal'), $definitionId, $versionId, $action, (int) $input['expected_version'], $input['note'] ?? null, (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }
}
