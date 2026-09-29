<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

final readonly class CatalogResourceDefinition implements CatalogResourceDefinitionInterface
{
    public function map(string $resource): array
    {
        if ($resource === CatalogResource::CommitmentSchedule->value) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $kind = $this->resource($resource);

        return [$kind->identityKey(), $kind->versionKey()];
    }

    public function resource(string $resource): CatalogResource
    {
        return CatalogResource::tryFrom($resource) ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
    }
}
