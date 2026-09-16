<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Illuminate\Validation\ValidationException;

final class LogoutAllRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['current_password', 'step_up_verification_token']);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required_without:step_up_verification_token', 'string'],
            'step_up_verification_token' => ['required_without:current_password', 'string'],
        ];
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (isset($input['current_password']) === isset($input['step_up_verification_token'])) {
            throw ValidationException::withMessages(['current_password' => ['Exactly one proof is required.']]);
        }
    }
}
