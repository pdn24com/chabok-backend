<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['verification_token' => ['required', 'string'], 'new_password' => ['required', 'string']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['verification_token', 'new_password']);
    }
}
