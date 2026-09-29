<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateCustomerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'family_name' => ['required', 'string', 'max:120'],
            'display_name' => ['required', 'string', 'max:200'],
            'customer_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'kind' => ['required', Rule::enum(CustomerKind::class)],
            'phase' => ['required', Rule::enum(CustomerPhase::class)],
            // The shape of the number is a domain rule; here only the column width is enforced.
            'mobile' => ['required', 'string', 'max:32'],
            'industry_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/D'],
            'province_id' => ['required_if:country_code,IR', 'prohibited_unless:country_code,IR', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'city_id' => ['required_if:country_code,IR', 'prohibited_unless:country_code,IR', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'foreign_city' => ['required_unless:country_code,IR', 'prohibited_if:country_code,IR', 'nullable', 'string', 'max:200'],
            'postal_code' => $this->postalCodeRules(),
            'address_text' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('country_code'))) {
            $this->merge(['country_code' => strtoupper(trim($this->input('country_code')))]);
        }
    }

    /** An Iranian postal code is exactly ten digits; every other country keeps its own spelling. */
    private function postalCodeRules(): array
    {
        return $this->input('country_code') === 'IR'
            ? ['sometimes', 'nullable', 'string', 'regex:/^\d{10}$/D']
            : ['sometimes', 'nullable', 'string', 'max:10'];
    }
}
