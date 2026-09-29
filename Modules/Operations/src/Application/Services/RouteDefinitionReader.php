<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\RouteDefinitionReaderInterface;
use Modules\Operations\Application\Dto\RouteLegDto;
use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class RouteDefinitionReader implements RouteDefinitionReaderInterface
{
    public function __construct(
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    /** @return list<RouteLegDto> */
    public function legInputs(string $hq, string $versionId): array
    {
        $version = $this->routeDefinitionRepository->findVersionWithLegs($hq, $versionId);
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'operations.source_version_not_found');
        }

        return RouteVersionDto::fromRecord($version)->legs;
    }

    public function versionRow(
        string $hq,
        string $definition,
        string $version,
    ): RouteDefinitionVersionRecord {
        $row = $this->routeDefinitionRepository->findDefinitionVersionWithLegs($hq, $definition, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }

    public function lockedVersion(
        string $hq,
        string $definition,
        string $version,
    ): RouteDefinitionVersionRecord {
        $row = $this->routeDefinitionRepository->lockDefinitionVersionWithLegs($hq, $definition, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
