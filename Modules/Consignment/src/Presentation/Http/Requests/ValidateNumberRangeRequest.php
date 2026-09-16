<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ValidateNumberRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['numeric_prefix', 'total_length', 'serial_start', 'serial_end']);
    }

    public function rules(): array
    {
        return [
            'numeric_prefix' => ['required', 'string', 'max:100'],
            'total_length' => ['required'],
            'serial_start' => ['required', 'string', 'max:100'],
            'serial_end' => ['required', 'string', 'max:100'],
        ];
    }
}
