<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteDefinitions;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListRouteDefinitionsHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
    )
    {
    }

    public function handle(ListRouteDefinitionsCommand $command): ListRouteDefinitionsResult
    {
        return new ListRouteDefinitionsResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        return $this->routes->definitions($hq, $filters);
    }
}
