<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** @mixin CustomerRecord */
final class CustomerListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new CustomerIdentityResource($this->resource))->resolve($request),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
