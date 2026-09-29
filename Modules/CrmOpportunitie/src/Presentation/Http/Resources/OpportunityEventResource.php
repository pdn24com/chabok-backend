<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityEventRecord;

/**
 * One move in the history of an opportunity. The codes and titles are the snapshot taken at the moment
 * of the move, so a step renamed since still reads here as it read then. The whole from_* group is null
 * on the entry that opened the opportunity and complete on every later one.
 *
 * @mixin OpportunityEventRecord
 */
final class OpportunityEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'opportunity_event_id' => $this->opportunity_event_id,
            'opportunity_id' => $this->opportunity_id,
            'from_step_id' => $this->from_step_id,
            'from_step_code' => $this->from_step_code,
            'from_step_title' => $this->from_step_title,
            'from_outcome_type' => $this->from_outcome_type?->value,
            'to_step_id' => $this->to_step_id,
            'to_step_code' => $this->to_step_code,
            'to_step_title' => $this->to_step_title,
            'to_outcome_type' => $this->to_outcome_type->value,
            'funnel_id' => $this->to_funnel_id,
            'funnel_title' => $this->to_funnel_title,
            'reason' => $this->reason,
            'evidence_activity_id' => $this->evidence_activity_id,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor === null ? null
                : ['user_id' => $this->actor->user_id, 'display_name' => $this->actor->display_name]),
        ];
    }
}
