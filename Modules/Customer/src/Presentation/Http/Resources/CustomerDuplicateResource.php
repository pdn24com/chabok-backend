<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\Dto\CustomerDuplicateDto;

/** @mixin CustomerDuplicateDto */
final class CustomerDuplicateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customerId,
            'display_name' => $this->displayName,
            'phase' => $this->phase->value,
            'matched_on' => $this->matchedOn,
        ];
    }
}
