<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/**
 * A row of the catalog table: what the item is, where it is filed and which industries it serves.
 *
 * @mixin CatalogItemRecord
 */
final class CatalogItemListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'catalog_item_id' => $this->catalog_item_id,
            'code' => $this->code,
            'title' => $this->title,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'category' => $this->category === null ? null : [
                'catalog_category_id' => $this->category->catalog_category_id,
                'title' => $this->category->title,
            ],
            'industries' => CatalogItemResource::industries($this->resource),
        ];
    }
}
