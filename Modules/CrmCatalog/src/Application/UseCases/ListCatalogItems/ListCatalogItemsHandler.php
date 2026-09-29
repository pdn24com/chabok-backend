<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogItems;

use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemRepositoryInterface;

final readonly class ListCatalogItemsHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogItemRepositoryInterface $catalogItemRepository,
    ) {}

    public function handle(ListCatalogItemsCommand $command): ListCatalogItemsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListCatalogItemsResult($this->catalogItemRepository->listForTenant($hqId, $command->filters));
    }
}
