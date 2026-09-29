<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'scope_type' => ['required', 'in:TENANT,AREA,NODE'],
            'scope_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'includes_descendants' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['role_id', 'scope_type', 'scope_id', 'includes_descendants']);
    }
}
