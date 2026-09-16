<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ConfirmManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'acknowledge_partial_success' => ['required', 'accepted'],
            'exception_reason_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'exception_description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'acknowledge_partial_success', 'exception_reason_code', 'exception_description']);
    }
}
