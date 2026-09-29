<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['challenge_id' => ['required', 'integer', 'min:1', 'max:4294967295'], 'code' => ['required', 'string', 'min:4', 'max:12']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['challenge_id', 'code']);
    }
}
