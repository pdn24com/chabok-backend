<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Authorization\Application\RoleNavigation;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CloneRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['role_code', 'role_title', 'description', 'permission_codes', 'menu_keys']);
    }

    public function rules(): array
    {
        return [
            'role_code' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'role_title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permission_codes' => ['sometimes', 'array'],
            'permission_codes.*' => ['required', 'string', 'distinct'],
            'menu_keys' => ['sometimes', 'nullable', 'array', 'max:27'],
            'menu_keys.*' => ['required', 'string', 'distinct', Rule::in(RoleNavigation::keys())],
        ];
    }
}
