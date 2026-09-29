<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateContractRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['required', 'string', 'max:120'],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'proforma_version_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            // Calendar days; the end is judged against the start in the handler.
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            // Rial amounts have no minor unit, so the amount is a whole number and never negative.
            'amount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            'commitments' => ['sometimes', 'nullable', 'string', 'max:20000'],
            // The status list is not final, so no enum: an UPPER_SNAKE label that fits the column.
            'status' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z][A-Z0-9_]{0,39}$/D'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];
        foreach (['reference_no', 'status'] as $field) {
            if (is_string($this->input($field))) {
                $trimmed[$field] = trim($this->input($field));
            }
        }
        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }
}
