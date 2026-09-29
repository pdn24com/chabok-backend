<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateCompanyRelationshipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Exactly one of the two names the person; whether the ID is a PERSON of the tenant is judged by the handler.
            'person_customer_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', 'required_without:new_person', 'prohibits:new_person'],
            'new_person' => ['nullable', 'array', 'required_without:person_customer_id'],
            'new_person.first_name' => ['required_with:new_person', 'string', 'max:120'],
            'new_person.family_name' => ['required_with:new_person', 'string', 'max:120'],
            // The shape of the number is a domain rule; here only the column width is enforced.
            'new_person.mobile' => ['required_with:new_person', 'string', 'max:32'],
            'position_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'role_title' => ['required', 'string', 'max:200'],
            'decision_level' => ['nullable', 'string', 'max:80'],
            'signing_authority' => ['nullable', 'string', 'max:200'],
            // The window is a pair of calendar days; the order of the two is judged by the handler's validator.
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d'],
            'is_primary' => ['sometimes', 'boolean'],
            'replace_primary' => ['sometimes', 'boolean'],
        ];
    }
}
