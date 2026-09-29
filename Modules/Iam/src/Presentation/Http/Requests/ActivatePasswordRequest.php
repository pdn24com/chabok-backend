<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ActivatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_token' => ['required_without:invitation_token', 'string'],
            'invitation_token' => ['required_without:verification_token', 'string'],
            'new_password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['verification_token', 'invitation_token', 'new_password']);
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (isset($input['verification_token']) === isset($input['invitation_token'])) {
            throw ValidationException::withMessages(['verification_token' => ['Exactly one proof is required.']]);
        }
    }
}
