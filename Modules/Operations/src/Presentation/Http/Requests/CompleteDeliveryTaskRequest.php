<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CompleteDeliveryTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'recipient_name' => ['required', 'string', 'max:200'],
            'delivered_at' => ['required', 'date'],
            'proof_type' => ['required', 'in:MANUAL_CONFIRMATION'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'recipient_name', 'delivered_at', 'proof_type', 'note']);
    }
}
