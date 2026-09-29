<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class CreateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'area_title' => ['required', 'string', 'max:200'],
            'parent_area_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
