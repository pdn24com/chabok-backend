<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateCustomerAddressRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every field is optional: a PATCH carries only what the operator actually changed. The rules
            // that read one field against another are absent on purpose. The country may stay untouched
            // while a city arrives, so the country a rule would compare against is not in the payload;
            // the handler judges those pairings on the address the change leaves behind.
            'country_code' => ['sometimes', 'string', 'size:2', 'regex:/^[A-Z]{2}$/D'],
            'purpose' => ['sometimes', 'string', 'max:60'],
            'address_text' => ['sometimes', 'string', 'max:1000'],
            'province_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'foreign_region' => ['sometimes', 'nullable', 'string', 'max:200'],
            'foreign_city' => ['sometimes', 'nullable', 'string', 'max:200'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'plaque' => ['sometimes', 'nullable', 'string', 'max:40'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:40'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('country_code'))) {
            $this->merge(['country_code' => strtoupper(trim($this->input('country_code')))]);
        }
    }
}
