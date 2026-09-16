<?php

declare(strict_types=1);

namespace Modules\User\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'scope_id' => ['sometimes', 'nullable', 'uuid'],
            'node_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
