<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListCitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'province_id' => ['sometimes', 'nullable', 'uuid'],
            'province_code' => ['sometimes', 'nullable', 'string', 'max:4'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
