<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class AssignPickupTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'driver_id' => ['required', 'integer', 'min:1', 'max:4294967295']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'driver_id']);
    }
}
