<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Authorization\Application\Services\RoleNavigation;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'permission_codes' => ['sometimes', 'array'],
            'permission_codes.*' => ['required', 'string', 'distinct'],
            'menu_keys' => ['sometimes', 'nullable', 'array', 'max:27'],
            'menu_keys.*' => ['required', 'string', 'distinct', Rule::in(RoleNavigation::keys())],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['role_title', 'description', 'status', 'permission_codes', 'menu_keys']);
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if ($input === []) {
            throw ValidationException::withMessages(['role' => ['At least one role field is required.']]);
        }
    }
}
