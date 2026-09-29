<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class FailPickupTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'reason_code', 'reason']);
    }
}
