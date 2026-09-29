<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;

/** @mixin CustomerHistoryEntryDto */
final class CustomerHistoryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'category' => $this->category->value,
            // Two sources can hand out the same id, so the row is identified by its table as well.
            'entry_type' => $this->entryType,
            'entry_id' => $this->entryId,
            'title' => $this->title,
            'kind' => $this->kind,
            'status' => $this->status,
            'actor' => $this->actor,
            'amount' => $this->amount,
            'occurred_at' => $this->occurredAt?->toISOString(),
        ];
    }
}
