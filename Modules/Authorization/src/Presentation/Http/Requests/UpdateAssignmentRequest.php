<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['role_id', 'scope_type', 'scope_id', 'includes_descendants']);
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'uuid'],
            'scope_type' => ['required', 'in:TENANT,AREA,NODE'],
            'scope_id' => ['sometimes', 'nullable', 'uuid'],
            'includes_descendants' => ['required', 'boolean'],
        ];
    }
}
