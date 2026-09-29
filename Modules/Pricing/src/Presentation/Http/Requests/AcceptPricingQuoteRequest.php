<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class AcceptPricingQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'object_type' => ['required', 'string', 'max:80'],
            'object_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'input_fingerprint' => ['required', 'size:64'],
        ];
    }
}
