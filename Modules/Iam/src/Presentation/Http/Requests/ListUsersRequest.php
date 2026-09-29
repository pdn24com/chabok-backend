<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:254'],
            'status' => ['sometimes', 'nullable', 'in:INVITED,ACTIVE,SUSPENDED,DEACTIVATED'],
            'role_code' => ['sometimes', 'nullable', 'string'],
            'scope_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'node_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
