<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class SaveCustomerExtendedDetailsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A PUT replaces the whole set: whatever the form leaves out is cleared, so no field is required.
            // The person columns and the company columns share the row; which of them the form shows is a
            // question for the customer kind, not a reason to refuse the other half here.
            'salutation' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'legal_form' => ['nullable', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'registration_no' => ['nullable', 'string', 'max:80'],
            // A unix timestamp in seconds, bounded to dates a company registration can plausibly carry.
            'registration_date' => ['nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            'registration_place' => ['nullable', 'string', 'max:200'],
            'need_summary' => ['nullable', 'string', 'max:2000'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            'budget_known' => ['nullable', 'boolean'],
            'authority_note' => ['nullable', 'string', 'max:2000'],
            'need_confirmed' => ['nullable', 'boolean'],
            'timeframe' => ['nullable', 'string', 'max:2000'],
            'qualification_result' => ['nullable', 'string', 'max:40'],
        ];
    }
}
