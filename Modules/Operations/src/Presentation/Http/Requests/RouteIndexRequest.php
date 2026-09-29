<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class RouteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'purpose' => ['nullable', Rule::in(['TRUNK', 'LAST_MILE'])],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ];
    }
}
