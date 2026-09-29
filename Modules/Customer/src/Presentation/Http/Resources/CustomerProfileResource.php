<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/**
 * The field set the profile form loads and sends back, so the read and the update stay symmetric. The
 * assignee appears as the bare id the form submits rather than as the named block the list and detail
 * views render. updated_at rides along because the form shows when the record last changed, but it is
 * server owned and the update never accepts it as input.
 *
 * @mixin CustomerRecord
 */
final class CustomerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer_id,
            'kind' => $this->kind->value,
            'phase' => $this->phase->value,
            'lifecycle' => $this->lifecycle,
            'display_name' => $this->display_name,
            'customer_code' => $this->customer_code,
            'assignee_id' => $this->assignee_id,
            'primary_industry' => $this->industry($request),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function industry(Request $request): ?array
    {
        $link = $this->primaryIndustry;
        if ($link?->industry === null) {
            return null;
        }

        return [
            ...(new CustomerIndustryResource($link->industry))->resolve($request),
            'is_primary' => $link->is_primary,
        ];
    }
}
