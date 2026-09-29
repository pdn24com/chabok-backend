<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogCategories;

use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogCategoryRepositoryInterface;

final readonly class ListCatalogCategoriesHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogCategoryRepositoryInterface $catalogCategoryRepository,
    ) {}

    public function handle(ListCatalogCategoriesCommand $command): ListCatalogCategoriesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListCatalogCategoriesResult($this->catalogCategoryRepository->listForTenant($hqId, $command->activeOnly));
    }
}
