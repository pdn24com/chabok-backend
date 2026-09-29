<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

/** @mixin OpportunityRecord */
final class CustomerOpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $step = $this->currentStep;

        return [
            'opportunity_id' => $this->opportunity_id,
            'title' => $this->title,
            'step' => ['title' => $step?->title, 'outcome_type' => $step?->outcome_type->value],
            'amount' => $this->amount === null ? null : (int) $this->amount,
        ];
    }
}
