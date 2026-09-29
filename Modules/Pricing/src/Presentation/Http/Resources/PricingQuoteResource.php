<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteLineRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;

/** @mixin PricingQuoteRecord */
final class PricingQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->attributesToArray(),
            'lines' => $this->resource->lines->map(static fn (PricingQuoteLineRecord $line): array => [
                ...$line->attributesToArray(),
                'category' => $line->chargeType->category,
            ])->all(),
        ];
    }
}
