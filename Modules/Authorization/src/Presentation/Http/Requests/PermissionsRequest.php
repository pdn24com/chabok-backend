<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class PermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['module_code' => ['sometimes', 'nullable', 'string', 'max:80']];
    }
}
