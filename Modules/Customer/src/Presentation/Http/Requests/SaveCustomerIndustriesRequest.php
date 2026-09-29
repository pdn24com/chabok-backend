<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class SaveCustomerIndustriesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The body is the complete set the customer should carry; an empty list removes every industry.
            // That the industries are distinct, active and hold at most one primary is judged by the handler.
            'items' => ['present', 'array', 'max:50'],
            'items.*' => ['array'],
            'items.*.industry_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'items.*.is_primary' => ['required', 'boolean'],
        ];
    }
}
