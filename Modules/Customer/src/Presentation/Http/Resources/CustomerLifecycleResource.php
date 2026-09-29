<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\UseCases\ChangeCustomerLifecycle\ChangeCustomerLifecycleResult;

/** @mixin ChangeCustomerLifecycleResult */
final class CustomerLifecycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customerId,
            'lifecycle' => $this->lifecycle->value,
            'activity_id' => $this->activityId,
        ];
    }
}
