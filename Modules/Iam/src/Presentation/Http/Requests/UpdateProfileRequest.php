<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'display_name' => ['sometimes', 'string', 'max:240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['first_name', 'last_name', 'display_name']);
    }

    protected function passedValidation(): void
    {
        if ($this->validated() === []) {
            throw ValidationException::withMessages(['profile' => ['At least one profile field is required.']]);
        }
    }
}
