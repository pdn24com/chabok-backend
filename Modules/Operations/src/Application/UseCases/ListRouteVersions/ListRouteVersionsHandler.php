<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteVersions;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ListRouteVersionsHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
    )
    {
    }

    public function handle(ListRouteVersionsCommand $command): ListRouteVersionsResult
    {
        return new ListRouteVersionsResult($this->execute($command->actor, $command->definitionId, $command->page, $command->perPage));
    }

    private function execute(AuthenticatedPrincipal $actor, string $definitionId, int $page = 1, int $perPage = 20): Page
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        if (!$this->routes->definitionExists($hq, $definitionId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->routes->history($hq, $definitionId, $page, $perPage);
    }
}
