<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogPersonaRecord;

/** @mixin CatalogPersonaRecord */
final class CatalogPersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'catalog_persona_id' => $this->catalog_persona_id,
            'code' => $this->code,
            'title' => $this->title,
            'is_active' => $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
