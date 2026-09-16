<?php

declare(strict_types=1);

namespace Modules\User\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['first_name', 'last_name', 'display_name']);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'display_name' => ['sometimes', 'string', 'max:240'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->validated() === []) {
            throw ValidationException::withMessages(['profile' => ['At least one profile field is required.']]);
        }
    }
}
