<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class CalculatePricingQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PricingRequestRules::quoteRules();
    }
}
