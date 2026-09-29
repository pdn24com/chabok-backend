<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListFleetDriversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return FleetListRules::rules() + [
            'unlinked' => ['sometimes', 'boolean'],
            'capability' => ['sometimes', 'nullable', 'in:PICKUP,LINEHAUL,DELIVERY'],
        ];
    }
}
