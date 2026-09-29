<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\FunnelStepRecord;

/**
 * One step of a funnel, which is one column of the board. outcome_type is what the client reads to tell
 * a closing column from an open one; the title is free text and never carries that meaning.
 *
 * @mixin FunnelStepRecord
 */
final class FunnelStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sales_funnel_step_id' => $this->sales_funnel_step_id,
            'code' => $this->code,
            'title' => $this->title,
            'sort_order' => (int) $this->sort_order,
            'outcome_type' => $this->outcome_type->value,
            'is_active' => $this->is_active,
        ];
    }
}
