<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveRouteDefinition;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class ResolveRouteDefinitionHandler
{
    public function __construct(
        private ClockInterface $clock,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(ResolveRouteDefinitionCommand $command): RouteDefinitionVersionRecord
    {
        $hqId = $command->hqId;
        $purpose = $command->purpose;
        $originNodeId = $command->originNodeId;
        $destinationNodeId = $command->destinationNodeId;
        $offeringVersionId = $command->offeringVersionId;
        $at = $command->at;
        $at ??= $this->clock->now();
        $matches = $this->routeDefinitionRepository->effectiveVersionsForLane($hqId, $purpose, $originNodeId, $destinationNodeId, $offeringVersionId, $at);
        if ($matches->isEmpty()) {
            throw new ApiException(ApiErrorCode::RouteNotFound, 422, 'operations.no_published_route_definition_matches_request');
        }
        $best = $matches->first();
        $ties = $matches->where('priority', $best->priority);
        if (count($ties) > 1) {
            throw new ApiException(ApiErrorCode::RouteAmbiguous, 422, 'operations.more_than_one_published_route_definition_has', details: ['route_definition_version_ids' => $ties->pluck('route_definition_version_id')->values()->all()]);
        }

        return $best;
    }
}
