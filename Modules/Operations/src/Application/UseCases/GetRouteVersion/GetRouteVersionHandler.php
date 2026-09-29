<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteVersion;

use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Contracts\RouteDefinitionReaderInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class GetRouteVersionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private RouteDefinitionReaderInterface $routeDefinitionReader,
    ) {}

    public function handle(GetRouteVersionCommand $command): RouteDefinitionVersionRecord
    {
        $actor = $command->actor;
        $definitionId = $command->definitionId;
        $versionId = $command->versionId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.view');

        return $this->routeDefinitionReader->versionRow($hq, $definitionId, $versionId);
    }
}
