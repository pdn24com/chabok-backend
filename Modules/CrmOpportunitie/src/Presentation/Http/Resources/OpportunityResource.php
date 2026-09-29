<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

/**
 * One opportunity as a write answers with it, naming the step it now stands on. close_reason is filled
 * only while the opportunity stands on a step that closes it.
 *
 * @mixin OpportunityRecord
 */
final class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'opportunity_id' => $this->opportunity_id,
            'customer_id' => $this->customer_id,
            'funnel_id' => $this->funnel_id,
            'title' => $this->title,
            'assignee_id' => $this->assignee_id,
            'amount' => $this->amount,
            'probability' => $this->probability,
            'expected_close' => $this->expected_close?->getTimestamp(),
            'close_reason' => $this->close_reason,
            'current_step_id' => $this->current_step_id,
            'current_step' => $this->whenLoaded('currentStep', fn (): ?array => $this->currentStep === null ? null
                : (new FunnelStepResource($this->currentStep))->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
