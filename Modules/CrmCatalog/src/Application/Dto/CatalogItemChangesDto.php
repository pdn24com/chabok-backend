<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Dto;

use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;

/**
 * A PATCH of one catalog item. Code, title, kind and status cannot be cleared, so a null simply means
 * "left alone"; every field that may be cleared carries its own *Specified flag, so an explicit null is
 * told apart from an absent key. A null industry list leaves the industries alone, while a list, even an
 * empty one, replaces the whole set.
 */
final readonly class CatalogItemChangesDto
{
    /**
     * @param  list<string>|null  $industryIds
     */
    public function __construct(
        public ?string $code = null,
        public ?string $title = null,
        public ?CatalogItemKind $kind = null,
        public ?CatalogItemStatus $status = null,
        public ?string $categoryId = null,
        public bool $categorySpecified = false,
        public ?string $buyerPersonaId = null,
        public bool $buyerPersonaSpecified = false,
        public ?string $salesModelId = null,
        public bool $salesModelSpecified = false,
        public ?string $description = null,
        public bool $descriptionSpecified = false,
        public ?string $deliveryTerms = null,
        public bool $deliveryTermsSpecified = false,
        public ?string $leadTime = null,
        public bool $leadTimeSpecified = false,
        public ?string $afterSalesPolicy = null,
        public bool $afterSalesPolicySpecified = false,
        public ?string $slaDescription = null,
        public bool $slaDescriptionSpecified = false,
        public ?string $warrantyDescription = null,
        public bool $warrantyDescriptionSpecified = false,
        public ?string $legalNotes = null,
        public bool $legalNotesSpecified = false,
        public ?array $industryIds = null,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->code === null && $this->title === null && $this->kind === null && $this->status === null
            && $this->industryIds === null
            && ! $this->categorySpecified && ! $this->buyerPersonaSpecified && ! $this->salesModelSpecified
            && ! $this->descriptionSpecified && ! $this->deliveryTermsSpecified && ! $this->leadTimeSpecified
            && ! $this->afterSalesPolicySpecified && ! $this->slaDescriptionSpecified
            && ! $this->warrantyDescriptionSpecified && ! $this->legalNotesSpecified;
    }

    /** The item as it will stand once the change lands. */
    public function applyTo(CatalogItemDraftDto $current): CatalogItemDraftDto
    {
        return new CatalogItemDraftDto(
            $this->code ?? $current->code,
            $this->title ?? $current->title,
            $this->kind ?? $current->kind,
            $this->status ?? $current->status,
            $this->categorySpecified ? $this->categoryId : $current->categoryId,
            $this->buyerPersonaSpecified ? $this->buyerPersonaId : $current->buyerPersonaId,
            $this->salesModelSpecified ? $this->salesModelId : $current->salesModelId,
            $this->descriptionSpecified ? $this->description : $current->description,
            $this->deliveryTermsSpecified ? $this->deliveryTerms : $current->deliveryTerms,
            $this->leadTimeSpecified ? $this->leadTime : $current->leadTime,
            $this->afterSalesPolicySpecified ? $this->afterSalesPolicy : $current->afterSalesPolicy,
            $this->slaDescriptionSpecified ? $this->slaDescription : $current->slaDescription,
            $this->warrantyDescriptionSpecified ? $this->warrantyDescription : $current->warrantyDescription,
            $this->legalNotesSpecified ? $this->legalNotes : $current->legalNotes,
            $this->industryIds ?? $current->industryIds,
        );
    }
}
