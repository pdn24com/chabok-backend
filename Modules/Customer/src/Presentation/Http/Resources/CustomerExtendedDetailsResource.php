<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;

/**
 * The supplementary-details form loads and sends back this complete field set, so the read and write stay
 * symmetric.
 * birth_date and registration_date travel as unix timestamps in seconds, midnight UTC of the stored
 * calendar day. updated_at rides along because the form shows when the record last changed, but it is
 * server owned and the write never accepts it as input; it stays null until the first save.
 *
 * @mixin CustomerExtendedDetailRecord
 */
final class CustomerExtendedDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer_id,
            'salutation' => $this->salutation,
            'birth_date' => $this->birth_date?->getTimestamp(),
            'trade_name' => $this->trade_name,
            'legal_form' => $this->legal_form,
            'legal_name' => $this->legal_name,
            'registration_no' => $this->registration_no,
            'registration_date' => $this->registration_date?->getTimestamp(),
            'registration_place' => $this->registration_place,
            'need_summary' => $this->need_summary,
            'budget' => $this->budget,
            'budget_known' => $this->budget_known,
            'authority_note' => $this->authority_note,
            'need_confirmed' => $this->need_confirmed,
            'timeframe' => $this->timeframe,
            'qualification_result' => $this->qualification_result,
            'evaluated_by' => $this->whenLoaded('evaluatedBy', fn (): ?array => $this->evaluatedBy === null ? null
                : (new CustomerAssigneeResource($this->evaluatedBy))->resolve($request)),
            'evaluated_at' => $this->evaluated_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
