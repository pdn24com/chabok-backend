<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptPricingQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid'],
            'object_type' => ['required', 'string', 'max:80'],
            'object_id' => ['required', 'uuid'],
            'input_fingerprint' => ['required', 'size:64'],
        ];
    }
}
