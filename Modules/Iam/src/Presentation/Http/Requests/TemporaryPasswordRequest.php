<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class TemporaryPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['temporary_password' => ['required', 'string']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['temporary_password']);
    }
}
