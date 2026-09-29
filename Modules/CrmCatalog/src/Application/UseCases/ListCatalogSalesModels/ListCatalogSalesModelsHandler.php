<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogSalesModels;

use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogSalesModelRepositoryInterface;

final readonly class ListCatalogSalesModelsHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogSalesModelRepositoryInterface $catalogSalesModelRepository,
    ) {}

    public function handle(ListCatalogSalesModelsCommand $command): ListCatalogSalesModelsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListCatalogSalesModelsResult($this->catalogSalesModelRepository->listForTenant($hqId, $command->activeOnly));
    }
}
