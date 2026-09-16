<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPublishedCatalogVersionsHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
    )
    {
    }

    public function handle(ListPublishedCatalogVersionsCommand $command): ListPublishedCatalogVersionsResult
    {
        return new ListPublishedCatalogVersionsResult($this->execute($command->actor, $command->resource, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $resource, array $filters): Page
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.view');
        $this->catalogResourceDefinition->map($resource);
        $page = $this->catalog->listPublishedVersions($actor->hqId, $resource, $filters);
        return new Page(array_map(fn($row) => $this->catalogReader->decode((array) $row), $page->rows), $page->page, $page->pageSize, $page->totalRows);
    }
}
