<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;

final readonly class ListPublishedCatalogVersionsHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(ListPublishedCatalogVersionsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $filters = $command->filters;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.view');
        $this->catalogResourceDefinition->map($resource);
        $kind = $this->catalogResourceDefinition->resource($resource);
        $versionKey = $kind->versionKey();
        $included = array_values(array_unique(array_map('strval', (array) ($filters['include_version_ids'] ?? []))));
        $identities = $this->catalogRepository->identitiesWithLatestVersion($kind, $included);
        foreach ($included as &$reference) {
            $reference = $identities->get($reference)?->latestVersion?->getAttribute($versionKey) ?? $reference;
        }
        unset($reference);
        $page = $this->catalogRepository->paginateAvailableVersions($kind, (string) $actor->hqId, $included,
            ($filters['search'] ?? '') === '' ? null : '%'.addcslashes((string) $filters['search'], '%_\\').'%',
            max(1, (int) ($filters['page'] ?? 1)), min(100, max(1, (int) ($filters['page_size'] ?? 100))));

        return $page->through(fn ($version) => CatalogDocument::version($version, withChildren: false, withIdentityStatus: true));
    }
}
