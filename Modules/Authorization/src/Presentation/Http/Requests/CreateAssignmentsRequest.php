<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.role_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'assignments.*.scope_type' => ['required', 'in:PLATFORM,TENANT,AREA,NODE,VENDOR,VENDOR_BRANCH,SELF'],
            'assignments.*.scope_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'assignments.*.includes_descendants' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['assignments']);
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        StrictPayload::assertItemsOnly($input['assignments'], ['role_id', 'scope_type', 'scope_id', 'includes_descendants'], 'assignments');
    }
}
