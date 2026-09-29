<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\SalesFunnelRecord;

/**
 * One sales pipeline with the steps the board draws its columns from. The steps appear only where the
 * relation was read; elsewhere the funnel stands on its own.
 *
 * @mixin SalesFunnelRecord
 */
final class SalesFunnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sales_funnel_id' => $this->sales_funnel_id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'catalog_item_id' => $this->catalog_item_id,
            'is_active' => $this->is_active,
            'steps' => $this->whenLoaded('steps', fn (): array => FunnelStepResource::collection($this->steps)->resolve($request)),
        ];
    }
}
