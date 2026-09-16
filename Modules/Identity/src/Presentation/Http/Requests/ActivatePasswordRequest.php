<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Illuminate\Validation\ValidationException;

final class ActivatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['verification_token', 'invitation_token', 'new_password']);
    }

    public function rules(): array
    {
        return [
            'verification_token' => ['required_without:invitation_token', 'string'],
            'invitation_token' => ['required_without:verification_token', 'string'],
            'new_password' => ['required', 'string'],
        ];
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (isset($input['verification_token']) === isset($input['invitation_token'])) {
            throw ValidationException::withMessages(['verification_token' => ['Exactly one proof is required.']]);
        }
    }
}
