<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory;

use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;

final readonly class GetCatalogHistoryHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private CatalogReaderInterface $catalogReader,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(GetCatalogHistoryCommand $command): array
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $identityIdValue = $command->identityIdValue;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.history.view');
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);
        $this->catalogReader->visibleIdentity($actor, $resource, $identityIdValue);

        return array_map(fn ($version) => CatalogDocument::version($version),
            $this->catalogRepository->versionHistoryOf($this->catalogResourceDefinition->resource($resource), $identityIdValue, (string) $actor->hqId));
    }
}
