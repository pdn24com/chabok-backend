<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class CreatePricingChargeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'in:BASE_FREIGHT,PICKUP_FEE,DELIVERY_FEE,REMOTE_AREA,EXTRA_PARCEL,INSURANCE,INSURANCE_FEE,COD_FEE,FUEL_SURCHARGE,DISCOUNT,TAX,COMMISSION',
            ],
            'category' => ['required', 'in:BASE,SURCHARGE,DISCOUNT,TAX,COMMISSION'],
            'accounting_mapping_key' => ['required', 'string', 'max:120'],
            'taxable' => ['boolean'],
            'active' => ['boolean'],
        ];
    }
}
