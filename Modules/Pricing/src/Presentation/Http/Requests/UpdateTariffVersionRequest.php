<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTariffVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PricingRequestRules::tariffRules(false) + ['expected_version' => ['required', 'integer', 'min:1']];
    }
}
