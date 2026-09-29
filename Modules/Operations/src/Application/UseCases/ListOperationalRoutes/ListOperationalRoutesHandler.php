<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListOperationalRoutes;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\OperationalDirectoryAccessInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;

final readonly class ListOperationalRoutesHandler
{
    public function __construct(
        private OperationalDirectoryAccessInterface $operationalDirectoryAccess,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    /** @return Collection<int, RouteDefinitionRecord> */
    public function handle(ListOperationalRoutesCommand $command): Collection
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->operationalDirectoryAccess->access($actor, $nodeId, 'live_operations.view', 'LiveOperations');

        return $this->routeDefinitionRepository->activeDefinitionsWithLegs($actor->hqId);
    }
}
