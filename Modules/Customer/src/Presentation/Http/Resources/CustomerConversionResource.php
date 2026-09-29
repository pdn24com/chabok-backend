<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** @mixin CustomerRecord */
final class CustomerConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer_id,
            'phase' => $this->phase->value,
            'converted_at' => $this->converted_at?->toISOString(),
            'customer_code' => $this->customer_code,
            // Merging into an existing customer is not available yet, so a conversion never merges.
            'merged_into' => null,
        ];
    }
}
