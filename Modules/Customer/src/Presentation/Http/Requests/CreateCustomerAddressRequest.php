<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateCustomerAddressRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The address form demands the country, what the address is for, and the written address.
            // Everything below it is optional, exactly as the nullable columns behind them allow.
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/D'],
            'purpose' => ['required', 'string', 'max:60'],
            'address_text' => ['required', 'string', 'max:1000'],
            'province_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295', 'prohibited_unless:country_code,IR', 'required_with:city_id'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295', 'prohibited_unless:country_code,IR'],
            'foreign_region' => ['sometimes', 'nullable', 'string', 'max:200', 'prohibited_if:country_code,IR'],
            'foreign_city' => ['sometimes', 'nullable', 'string', 'max:200', 'prohibited_if:country_code,IR'],
            // The ten-digit Iranian shape is a domain rule; here only the column width is enforced.
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            // Text, so a leading zero or a letter in a plaque or unit number survives.
            'plaque' => ['sometimes', 'nullable', 'string', 'max:40'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:40'],
            // The pair travels together; the database keeps both set or both null.
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
