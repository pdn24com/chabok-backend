<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogCategoryRecord;

/** @mixin CatalogCategoryRecord */
final class CatalogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'catalog_category_id' => $this->catalog_category_id,
            'code' => $this->code,
            'title' => $this->title,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
