<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Validators;

use Modules\CrmCatalog\Application\Contracts\CatalogItemValidatorInterface;
use Modules\CrmCatalog\Application\Dto\CatalogItemChangesDto;
use Modules\CrmCatalog\Application\Dto\CatalogItemDraftDto;
use Modules\CrmCatalog\Application\Repositories\CatalogCategoryRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogPersonaRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogSalesModelRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CatalogItemValidator implements CatalogItemValidatorInterface
{
    public function __construct(
        private CatalogCategoryRepositoryInterface $catalogCategoryRepository,
        private CatalogPersonaRepositoryInterface $catalogPersonaRepository,
        private CatalogSalesModelRepositoryInterface $catalogSalesModelRepository,
        private IndustryRepositoryInterface $industryRepository,
    ) {}

    public function validate(string $hqId, CatalogItemDraftDto $draft): void
    {
        $this->assertReferences($hqId, $draft->categoryId, $draft->buyerPersonaId, $draft->salesModelId, $draft->industryIds);
    }

    public function validateChanges(string $hqId, CatalogItemChangesDto $changes, CatalogItemDraftDto $current): void
    {
        $this->assertReferences(
            $hqId,
            $changes->categorySpecified && $changes->categoryId !== $current->categoryId ? $changes->categoryId : null,
            $changes->buyerPersonaSpecified && $changes->buyerPersonaId !== $current->buyerPersonaId ? $changes->buyerPersonaId : null,
            $changes->salesModelSpecified && $changes->salesModelId !== $current->salesModelId ? $changes->salesModelId : null,
            $changes->industryIds ?? [],
            $current->industryIds,
        );
    }

    /**
     * @param  list<string>  $industryIds
     * @param  list<string>  $keptIndustryIds  Industries the item is already linked to; they need not be active any more.
     */
    private function assertReferences(string $hqId, ?string $categoryId, ?string $buyerPersonaId, ?string $salesModelId, array $industryIds, array $keptIndustryIds = []): void
    {
        if ($categoryId !== null && ! $this->catalogCategoryRepository->activeExistsInTenant($hqId, $categoryId)) {
            throw $this->invalid('category_id', 'catalog.select_active_category');
        }
        if ($buyerPersonaId !== null && ! $this->catalogPersonaRepository->activeExistsInTenant($hqId, $buyerPersonaId)) {
            throw $this->invalid('buyer_persona_id', 'catalog.select_active_buyer_persona');
        }
        if ($salesModelId !== null && ! $this->catalogSalesModelRepository->activeExistsInTenant($hqId, $salesModelId)) {
            throw $this->invalid('sales_model_id', 'catalog.select_active_sales_model');
        }
        if (count(array_unique($industryIds)) !== count($industryIds)) {
            throw $this->invalid('industry_ids', 'catalog.industries_are_named_once');
        }
        $toCheck = array_values(array_diff($industryIds, $keptIndustryIds));
        if ($toCheck !== [] && count($this->industryRepository->activeIdsInTenant($hqId, $toCheck)) !== count($toCheck)) {
            throw $this->invalid('industry_ids', 'catalog.select_active_industries_from_reference_data');
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
