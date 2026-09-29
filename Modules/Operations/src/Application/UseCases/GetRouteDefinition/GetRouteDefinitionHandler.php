<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteDefinition;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;

final readonly class GetRouteDefinitionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(GetRouteDefinitionCommand $command): RouteDefinitionRecord
    {
        $actor = $command->actor;
        $id = $command->id;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.view');
        $row = $this->routeDefinitionRepository->findDefinition($hq, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
