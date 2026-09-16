<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteDefinition;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetRouteDefinitionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
    )
    {
    }

    public function handle(GetRouteDefinitionCommand $command): GetRouteDefinitionResult
    {
        return new GetRouteDefinitionResult($this->execute($command->actor, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $id): array
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        $row = $this->routes->definition($hq, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->routeDefinitionReader->definitionArray($row);
    }
}
