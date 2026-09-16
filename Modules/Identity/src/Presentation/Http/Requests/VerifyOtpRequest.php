<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['challenge_id', 'code']);
    }

    public function rules(): array
    {
        return ['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'min:4', 'max:12']];
    }
}
