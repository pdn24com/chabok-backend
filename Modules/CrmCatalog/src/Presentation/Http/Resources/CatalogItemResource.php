<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/**
 * The identity card of one catalog item: every field the form submits, with the category, persona and
 * sales model named beside their IDs. The reserved references and the channels are not emitted until O01.
 *
 * @mixin CatalogItemRecord
 */
final class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $industries = self::industries($this->resource);

        return [
            'catalog_item_id' => $this->catalog_item_id,
            'code' => $this->code,
            'title' => $this->title,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'category_id' => $this->category_id,
            'category' => $this->category === null ? null : [
                'catalog_category_id' => $this->category->catalog_category_id,
                'title' => $this->category->title,
            ],
            'buyer_persona_id' => $this->buyer_persona_id,
            'buyer_persona' => $this->buyerPersona === null ? null : [
                'catalog_persona_id' => $this->buyerPersona->catalog_persona_id,
                'title' => $this->buyerPersona->title,
            ],
            'sales_model_id' => $this->sales_model_id,
            'sales_model' => $this->salesModel === null ? null : [
                'catalog_sales_model_id' => $this->salesModel->catalog_sales_model_id,
                'title' => $this->salesModel->title,
            ],
            'description' => $this->description,
            'delivery_terms' => $this->delivery_terms,
            'lead_time' => $this->lead_time,
            'after_sales_policy' => $this->after_sales_policy,
            'sla_description' => $this->sla_description,
            'warranty_description' => $this->warranty_description,
            'legal_notes' => $this->legal_notes,
            'industry_ids' => array_column($industries, 'industry_id'),
            'industries' => $industries,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /** @return list<array{industry_id: string, title: string}> */
    public static function industries(CatalogItemRecord $item): array
    {
        $industries = [];
        foreach ($item->industryLinks as $link) {
            if ($link->industry !== null) {
                $industries[] = ['industry_id' => $link->industry->industry_id, 'title' => $link->industry->title];
            }
        }

        return $industries;
    }
}
