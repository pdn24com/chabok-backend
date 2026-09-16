<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['assignments']);
    }

    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.role_id' => ['required', 'uuid'],
            'assignments.*.scope_type' => ['required', 'in:PLATFORM,TENANT,AREA,NODE,VENDOR,VENDOR_BRANCH,SELF'],
            'assignments.*.scope_id' => ['sometimes', 'nullable', 'uuid'],
            'assignments.*.includes_descendants' => ['required', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        StrictPayload::assertItemsOnly($input['assignments'], ['role_id', 'scope_type', 'scope_id', 'includes_descendants'], 'assignments');
    }
}
