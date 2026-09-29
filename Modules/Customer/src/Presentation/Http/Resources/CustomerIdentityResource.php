<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/**
 * What names a customer wherever one is listed or opened: a list row adds the last change to it, the
 * detail view adds the conversion time and the open work counts.
 *
 * @mixin CustomerRecord
 */
final class CustomerIdentityResource extends JsonResource
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
            'assignee' => $this->assignee === null ? null : (new CustomerAssigneeResource($this->assignee))->resolve($request),
        ];
    }
}
