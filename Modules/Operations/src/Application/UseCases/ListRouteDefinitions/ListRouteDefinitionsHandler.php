<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteDefinitions;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;

final readonly class ListRouteDefinitionsHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(ListRouteDefinitionsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.view');

        return $this->routeDefinitionRepository->paginateDefinitions($hq, $filters);
    }
}
