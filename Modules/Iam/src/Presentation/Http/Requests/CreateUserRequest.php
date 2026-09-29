<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'creation_mode' => ['required', 'in:DIRECT_ACTIVE,SMS_INVITATION,EMAIL_INVITATION'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'temporary_password' => ['sometimes', 'string'],
            'assignments' => ['present', 'array', 'max:50'],
            'assignments.*.role_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'assignments.*.scope_type' => ['required', 'in:PLATFORM,TENANT,AREA,NODE,VENDOR,VENDOR_BRANCH,SELF'],
            'assignments.*.scope_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'assignments.*.includes_descendants' => ['required', 'boolean'],
        ] + OperationalProfileRules::rules((array) $this->input('operational_profile', []));
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, [
            'creation_mode',
            'username',
            'mobile',
            'email',
            'first_name',
            'last_name',
            'temporary_password',
            'assignments',
            'operational_profile',
        ]);
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        StrictPayload::assertItemsOnly($input['assignments'], ['role_id', 'scope_type', 'scope_id', 'includes_descendants'], 'assignments');
        if (empty($input['username']) && empty($input['mobile']) && empty($input['email'])) {
            throw ValidationException::withMessages(['identifier' => ['At least one identifier is required.']]);
        }
    }
}
