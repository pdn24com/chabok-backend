<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateOpportunityRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The customer may still be a lead; the pipeline is what promotes the one into the other.
            'customer_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'funnel_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'title' => ['required', 'string', 'max:200'],
            // Every opportunity has an owner: the column is not nullable and no fallback is invented here.
            'assignee_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            // A rial amount, so it stays a whole number and is never negative.
            'amount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            // A percentage stored as decimal(5,2); two decimal places is the column's whole precision.
            'probability' => ['sometimes', 'nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            // A unix timestamp in seconds; only the calendar day of it is kept.
            'expected_close' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            // The starting step belongs to the server, so the form is refused for naming one at all.
            'current_step_id' => ['prohibited'],
        ];
    }
}
