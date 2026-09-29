<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Dto;

use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/**
 * One catalog item as the identity-card form submits it. The reserved logical references (unit, sales
 * commitment, after-sales policy, SLA template) and the channels wait on owner decision O01, so the draft
 * neither accepts nor carries them.
 */
final readonly class CatalogItemDraftDto
{
    /**
     * @param  list<string>  $industryIds  The industries the item is offered to, each named once.
     */
    public function __construct(
        public string $code,
        public string $title,
        public CatalogItemKind $kind,
        public CatalogItemStatus $status = CatalogItemStatus::ACTIVE,
        public ?string $categoryId = null,
        public ?string $buyerPersonaId = null,
        public ?string $salesModelId = null,
        public ?string $description = null,
        public ?string $deliveryTerms = null,
        public ?string $leadTime = null,
        public ?string $afterSalesPolicy = null,
        public ?string $slaDescription = null,
        public ?string $warrantyDescription = null,
        public ?string $legalNotes = null,
        public array $industryIds = [],
    ) {}

    /**
     * The stored item as a draft, so a PATCH is applied to the whole card it leaves behind.
     *
     * @param  list<string>  $industryIds
     */
    public static function fromRecord(CatalogItemRecord $item, array $industryIds): self
    {
        return new self(
            $item->code,
            $item->title,
            $item->kind,
            $item->status,
            $item->category_id,
            $item->buyer_persona_id,
            $item->sales_model_id,
            $item->description,
            $item->delivery_terms,
            $item->lead_time,
            $item->after_sales_policy,
            $item->sla_description,
            $item->warranty_description,
            $item->legal_notes,
            $industryIds,
        );
    }

    /**
     * The item as the row that stores it. Ownership, the author and the creation time stay with the
     * caller, so the same map serves an insert and a full overwrite; the industries live in their own table.
     */
    public function toAttributes(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'category_id' => $this->categoryId,
            'buyer_persona_id' => $this->buyerPersonaId,
            'sales_model_id' => $this->salesModelId,
            'description' => $this->description,
            'delivery_terms' => $this->deliveryTerms,
            'lead_time' => $this->leadTime,
            'after_sales_policy' => $this->afterSalesPolicy,
            'sla_description' => $this->slaDescription,
            'warranty_description' => $this->warrantyDescription,
            'legal_notes' => $this->legalNotes,
        ];
    }
}
