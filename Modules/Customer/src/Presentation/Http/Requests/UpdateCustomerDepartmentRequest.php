<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateCustomerDepartmentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every field is optional: a PATCH carries only what the operator actually changed.
            'title' => ['sometimes', 'string', 'max:200'],
            'parent_department_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'cost_center_code' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
