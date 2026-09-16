<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveRouteDefinition;

use DateTimeInterface;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ResolveRouteDefinitionHandler
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
    )
    {
    }

    public function handle(ResolveRouteDefinitionCommand $command): ResolveRouteDefinitionResult
    {
        return new ResolveRouteDefinitionResult($this->execute($command->hqId, $command->purpose, $command->originNodeId, $command->destinationNodeId, $command->offeringVersionId, $command->at));
    }

    private function execute(
        string $hqId,
        string $purpose,
        string $originNodeId,
        string $destinationNodeId,
        ?string $offeringVersionId = null,
        ?DateTimeInterface $at = null,
    ): array
    {
        $at ??= $this->clock->now();
        $matches = $this->routes->matchingVersions($hqId, $purpose, $originNodeId, $destinationNodeId, $offeringVersionId, $at);
        if ($matches === []) {
            throw new ApiException(ApiErrorCode::RouteNotFound, 422, 'No published Route Definition matches the request.');
        }
        $best = $matches[0];
        $ties = array_values(array_filter($matches, fn($match) => $match->priority == $best->priority));
        if (count($ties) > 1) {
            throw new ApiException(ApiErrorCode::RouteAmbiguous, 422, 'More than one published Route Definition has the best priority.', details: ['route_definition_version_ids' => array_map(fn($tie) => $tie->route_definition_version_id, $ties)]);
        }
        return $this->routeDefinitionReader->versionArray($best);
    }
}
