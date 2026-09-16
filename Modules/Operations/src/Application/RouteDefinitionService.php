<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Application\Data\Page;
use DateTimeInterface;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RouteDefinitionService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsHandler $listRouteDefinitions,
        private \Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionHandler $createRouteDefinition,
        private \Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionHandler $getRouteDefinition,
        private \Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsHandler $listRouteVersions,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
        private \Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionHandler $createRouteVersion,
        private \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler $getRouteVersion,
        private \Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionHandler $updateRouteVersion,
        private \Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionHandler $transitionRouteVersion,
        private \Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionHandler $resolveRouteDefinition,
    )
    {
    }

    public function list(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listRouteDefinitions->handle(new \Modules\Operations\Application\UseCases\ListRouteDefinitions\ListRouteDefinitionsCommand($actor, $filters))->data;
    }

    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createRouteDefinition->handle(new \Modules\Operations\Application\UseCases\CreateRouteDefinition\CreateRouteDefinitionCommand($actor, $input, $correlationId))->data;
    }

    public function definition(AuthenticatedPrincipal $actor, string $id): array
    {
        return $this->getRouteDefinition->handle(new \Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionCommand($actor, $id))->data;
    }

    public function history(AuthenticatedPrincipal $actor, string $definitionId, int $page = 1, int $perPage = 20): Page
    {
        return $this->listRouteVersions->handle(new \Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsCommand($actor, $definitionId, $page, $perPage))->data;
    }

    public function presentVersion(object $row): array
    {
        return $this->routeDefinitionReader->presentVersion($row);
    }

    public function createVersion(AuthenticatedPrincipal $actor, string $definitionId, array $input, string $correlationId): array
    {
        return $this->createRouteVersion->handle(new \Modules\Operations\Application\UseCases\CreateRouteVersion\CreateRouteVersionCommand($actor, $definitionId, $input, $correlationId))->data;
    }

    public function version(AuthenticatedPrincipal $actor, string $definitionId, string $versionId): array
    {
        return $this->getRouteVersion->handle(new \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand($actor, $definitionId, $versionId))->data;
    }

    public function update(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
        array $input,
        string $correlationId,
    ): array
    {
        return $this->updateRouteVersion->handle(new \Modules\Operations\Application\UseCases\UpdateRouteVersion\UpdateRouteVersionCommand($actor, $definitionId, $versionId, $input, $correlationId))->data;
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
        string $action,
        int $expected,
        ?string $note,
        string $correlationId,
    ): array
    {
        return $this->transitionRouteVersion->handle(new \Modules\Operations\Application\UseCases\TransitionRouteVersion\TransitionRouteVersionCommand($actor, $definitionId, $versionId, $action, $expected, $note, $correlationId))->data;
    }

    public function resolve(
        string $hqId,
        string $purpose,
        string $originNodeId,
        string $destinationNodeId,
        ?string $offeringVersionId = null,
        ?DateTimeInterface $at = null,
    ): array
    {
        return $this->resolveRouteDefinition->handle(new \Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionCommand($hqId, $purpose, $originNodeId, $destinationNodeId, $offeringVersionId, $at))->data;
    }
}
