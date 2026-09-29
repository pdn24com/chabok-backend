<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

/**
 * One card on the board. The customer, the owner and the next open task are named only where their
 * relations were read; the card carries the IDs either way.
 *
 * @mixin OpportunityRecord
 */
final class OpportunityCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'opportunity_id' => $this->opportunity_id,
            'title' => $this->title,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : [
                'customer_id' => $this->customer->customer_id,
                'display_name' => $this->customer->display_name,
                'phase' => $this->customer->phase->value,
            ]),
            'assignee_id' => $this->assignee_id,
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null
                : ['user_id' => $this->assignee->user_id, 'display_name' => $this->assignee->display_name]),
            'amount' => $this->amount,
            // A decimal string, so the stored two-place precision is never rounded by a float.
            'probability' => $this->probability,
            'expected_close' => $this->expected_close?->getTimestamp(),
            'next_task' => $this->whenLoaded('nextTask', fn (): ?array => $this->nextTask === null ? null : [
                'task_id' => $this->nextTask->task_id,
                'title' => $this->nextTask->title,
                'status' => $this->nextTask->status->value,
                'due_at' => $this->nextTask->due_at?->toISOString(),
            ]),
        ];
    }
}
