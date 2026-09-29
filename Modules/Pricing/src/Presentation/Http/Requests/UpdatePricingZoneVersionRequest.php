<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class UpdatePricingZoneVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PricingRequestRules::zoneSetRules(false) + ['expected_version' => ['required', 'integer', 'min:1']];
    }
}
