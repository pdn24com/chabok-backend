<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\RouteDefinitionDto;
use Modules\Operations\Application\Dto\RouteDefinitionFiltersDto;
use Modules\Operations\Application\Dto\RouteVersionChangesDto;
use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionHandler;
use Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionCommand;
use Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionHandler;
use Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionHandler;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler;
use Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsCommand;
use Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsHandler;
use Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsCommand;
use Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsHandler;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionHandler;
use Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionCommand;
use Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionHandler;
use Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionCommand;
use Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionHandler;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Presentation\Http\Resources\RouteDefinitionResource;
use Modules\Operations\Presentation\Http\Resources\RouteVersionResource;

final readonly class RouteDefinitionFixtures
{
    public function __construct(
        private ListRouteDefinitionsHandler $listRouteDefinitions,
        private CreateRouteDefinitionHandler $createRouteDefinition,
        private GetRouteDefinitionHandler $getRouteDefinition,
        private ListRouteVersionsHandler $listRouteVersions,
        private CreateRouteVersionHandler $createRouteVersion,
        private GetRouteVersionHandler $getRouteVersion,
        private UpdateRouteVersionHandler $updateRouteVersion,
        private TransitionRouteVersionHandler $transitionRouteVersion,
        private ResolveRouteDefinitionHandler $resolveRouteDefinition,
    ) {}

    public function list(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listRouteDefinitions->handle(new ListRouteDefinitionsCommand($actor, RouteDefinitionFiltersDto::fromValidated($filters)));
    }

    public function create(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new RouteDefinitionResource($this->createRouteDefinition->handle(new CreateRouteDefinitionCommand($actor, RouteDefinitionDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function definition(AuthenticatedPrincipal $actor, string $id): array
    {
        return (new RouteDefinitionResource($this->getRouteDefinition->handle(new GetRouteDefinitionCommand($actor, $id))))->resolve();
    }

    public function history(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->listRouteVersions->handle(new ListRouteVersionsCommand($actor, $definitionId, $page, $perPage));
    }

    public function createVersion(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        array $input,
        string $correlationId,
    ): array {
        return (new RouteVersionResource($this->createRouteVersion->handle(new CreateRouteVersionCommand($actor, $definitionId, RouteVersionDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function version(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
    ): array {
        return (new RouteVersionResource($this->getRouteVersion->handle(new GetRouteVersionCommand($actor, $definitionId, $versionId))))->resolve();
    }

    public function update(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
        array $input,
        string $correlationId,
    ): array {
        return (new RouteVersionResource($this->updateRouteVersion->handle(new UpdateRouteVersionCommand($actor, $definitionId, $versionId, RouteVersionChangesDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
        string $action,
        int $expected,
        ?string $note,
        string $correlationId,
    ): array {
        return (new RouteVersionResource($this->transitionRouteVersion->handle(new TransitionRouteVersionCommand($actor, $definitionId, $versionId, $action, $expected, $note, $correlationId))))->resolve();
    }

    public function resolve(
        string $hqId,
        RoutePurpose $purpose,
        string $originNodeId,
        string $destinationNodeId,
        ?string $offeringVersionId = null,
        ?DateTimeInterface $at = null,
    ): array {
        return (new RouteVersionResource($this->resolveRouteDefinition->handle(new ResolveRouteDefinitionCommand($hqId, $purpose, $originNodeId, $destinationNodeId, $offeringVersionId, $at))))->resolve();
    }
}
