<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class CoverageIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'target' => ['nullable', Rule::in(['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE'])],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ];
    }
}
