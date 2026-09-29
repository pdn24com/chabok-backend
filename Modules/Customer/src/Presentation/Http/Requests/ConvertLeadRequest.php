<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ConvertLeadRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Every field is optional: the form only sends what the operator completed while converting.
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'family_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'customer_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            // Accepted only so that the handler can answer with the product's "not available yet".
            'merge_into_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'confirm_merge' => ['sometimes', 'boolean'],
        ];
    }
}
