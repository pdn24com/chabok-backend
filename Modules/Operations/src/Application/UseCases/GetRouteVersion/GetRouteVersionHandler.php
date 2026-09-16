<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetRouteVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
    )
    {
    }

    public function handle(GetRouteVersionCommand $command): GetRouteVersionResult
    {
        return new GetRouteVersionResult($this->execute($command->actor, $command->definitionId, $command->versionId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $definitionId, string $versionId): array
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        return $this->routeDefinitionReader->versionArray($this->routeDefinitionReader->versionRow($hq, $definitionId, $versionId));
    }
}
