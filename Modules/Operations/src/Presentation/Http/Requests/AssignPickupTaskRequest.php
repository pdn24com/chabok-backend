<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class AssignPickupTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'driver_id']);
    }

    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'driver_id' => ['required', 'uuid']];
    }
}
