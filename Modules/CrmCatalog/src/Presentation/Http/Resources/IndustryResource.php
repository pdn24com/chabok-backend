<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\IndustryRecord;

/** @mixin IndustryRecord */
final class IndustryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'industry_id' => $this->industry_id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
