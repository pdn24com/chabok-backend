<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class AssignmentOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['role_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295']];
    }
}
