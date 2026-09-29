<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateStepTransitionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Where the operator believed the opportunity stood: the guard against a concurrent move.
            'from_step_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'to_step_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // A unix timestamp in seconds; left out, the move is recorded as having happened now.
            'occurred_at' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            'evidence_activity_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'close_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
