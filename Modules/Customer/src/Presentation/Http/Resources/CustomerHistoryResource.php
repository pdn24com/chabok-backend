<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\Dto\CustomerHistoryCategoryDto;
use Modules\Customer\Application\Dto\CustomerHistoryDto;

/**
 * The history page of one customer: six cards and two plain observations. Neither figure carries an
 * approved threshold, so the payload reports them and declares no alert.
 *
 * @mixin CustomerHistoryDto
 */
final class CustomerHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer->customer_id,
            'categories' => array_map(fn (CustomerHistoryCategoryDto $card): array => [
                'category' => $card->category->value,
                'total' => $card->total,
                // Set when the category has no source at all, so the page explains the empty card.
                'unavailable_reason' => $card->category->unavailableReason(),
                'preview' => CustomerHistoryEntryResource::collection($card->preview)->resolve($request),
            ], $this->categories),
            'last_interaction_at' => $this->lastInteractionAt?->toISOString(),
            'days_since_last_interaction' => $this->daysSinceLastInteraction(new DateTimeImmutable),
            'open_work_count' => $this->openWorkCount,
        ];
    }
}
