<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['identifier', 'password', 'client_type', 'device_id', 'device_name']);
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string'],
            'client_type' => ['sometimes', 'in:BRANCH_PANEL'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:200'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
