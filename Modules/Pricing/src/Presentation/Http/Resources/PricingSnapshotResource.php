<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;

/** @mixin PricingSnapshotRecord */
final class PricingSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->resource->attributesToArray(), 'lines' => $this->resource->lines->map->attributesToArray()->all()];
    }
}
