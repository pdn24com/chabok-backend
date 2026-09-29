<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['identifier' => ['required', 'string', 'max:254'], 'purpose' => ['required', 'in:ACTIVATION,PASSWORD_RESET']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['identifier', 'purpose']);
    }
}
