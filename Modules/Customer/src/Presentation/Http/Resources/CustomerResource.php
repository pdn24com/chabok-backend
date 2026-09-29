<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** @mixin CustomerRecord */
final class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer_id,
            'hq_id' => $this->hq_id,
            'first_name' => $this->first_name,
            'family_name' => $this->family_name,
            'display_name' => $this->display_name,
            'customer_code' => $this->customer_code,
            'kind' => $this->kind->value,
            'phase' => $this->phase->value,
            'lifecycle' => $this->lifecycle,
            'mobile' => $this->whenLoaded('defaultMobile', fn (): ?string => $this->defaultMobile?->value),
            'industry_id' => $this->whenLoaded('primaryIndustry', fn (): ?string => $this->primaryIndustry?->industry_id),
            'assignee_id' => $this->assignee_id,
            'created_by' => $this->created_by,
            'converted_at' => $this->converted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'address' => new CustomerAddressResource($this->whenLoaded('defaultAddress')),
        ];
    }
}
