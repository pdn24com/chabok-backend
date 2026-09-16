<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ResubmitManifestExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'expected_exception_version' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'min:1', 'max:80'],
            'description' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'expected_exception_version', 'reason_code', 'description']);
    }
}
