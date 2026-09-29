<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;

final readonly class ListCatalogIdentitiesHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(ListCatalogIdentitiesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $filters = $command->filters;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.view');
        $this->catalogResourceDefinition->map($resource);
        $kind = $this->catalogResourceDefinition->resource($resource);
        $page = $this->catalogRepository->paginateIdentities($kind, $actor->hqId,
            ($filters['search'] ?? '') === '' ? null : (string) $filters['search'],
            ($filters['status'] ?? '') === '' ? null : (string) $filters['status'],
            max(1, (int) ($filters['page'] ?? 1)), min(100, max(1, (int) ($filters['page_size'] ?? 25))));

        return $page->through(fn ($identity) => [
            ...$identity->attributesToArray(),
            'latest_status' => $identity->latestVersion?->status,
            'latest_version_number' => $identity->latestVersion?->version_number,
            'latest_version_id' => $identity->latestVersion?->getAttribute($kind->versionKey()),
            'labels' => $identity->latestVersion?->labels,
        ]);
    }
}
