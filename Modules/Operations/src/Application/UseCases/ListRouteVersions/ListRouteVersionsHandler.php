<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteVersions;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;

final readonly class ListRouteVersionsHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(ListRouteVersionsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $definitionId = $command->definitionId;
        $page = $command->page;
        $perPage = $command->perPage;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.view');
        if (! $this->routeDefinitionRepository->definitionExists($hq, $definitionId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $this->routeDefinitionRepository->paginateVersions($hq, $definitionId, $page, $perPage);
    }
}
