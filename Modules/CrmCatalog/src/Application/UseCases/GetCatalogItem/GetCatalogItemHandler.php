<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\GetCatalogItem;

use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetCatalogItemHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogItemRepositoryInterface $catalogItemRepository,
    ) {}

    public function handle(GetCatalogItemCommand $command): GetCatalogItemResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $item = $this->catalogItemRepository->findForTenant($hqId, $command->catalogItemId);
        // An item of another tenant reads as one that does not exist.
        if ($item === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new GetCatalogItemResult($item);
    }
}
