<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;

/** One industry link of a customer with the industry's title. @mixin CustomerIndustryRecord */
final class CustomerIndustryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'industry_id' => $this->industry_id,
            'title' => $this->industry?->title,
            'is_primary' => $this->is_primary,
        ];
    }
}
