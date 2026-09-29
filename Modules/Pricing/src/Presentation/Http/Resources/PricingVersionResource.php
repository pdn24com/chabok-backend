<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Application\Serialization\PricingVersionSnapshot;

final class PricingVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return PricingVersionSnapshot::serialize($this->resource);
    }
}
