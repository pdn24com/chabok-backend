<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class CatalogReader implements CatalogReaderInterface
{
    public function __construct(
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function versionDetail(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
    ): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord {
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);
        $kind = $this->catalogResourceDefinition->resource($resource);
        $row = $this->catalogRepository->findVisibleVersionDetail($kind, $versionIdValue, (string) $actor->hqId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }

    public function visibleIdentity(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $value,
    ): void {
        $kind = $this->catalogResourceDefinition->resource($resource);
        if (! $this->catalogRepository->identityExists($kind, $value, $actor->hqId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
    }
}
