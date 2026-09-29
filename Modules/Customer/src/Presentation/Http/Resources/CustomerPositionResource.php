<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;

/** @mixin CustomerPositionRecord */
final class CustomerPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position_id' => $this->position_id,
            'department_id' => $this->department_id,
            'title' => $this->title,
            'decision_level' => $this->decision_level,
            // Descriptive only: the figure approves nothing and authorises no payment on its own.
            'delegation_limit' => $this->delegation_limit === null ? null : (int) $this->delegation_limit,
        ];
    }
}
