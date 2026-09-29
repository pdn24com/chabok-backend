<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateCustomerDepartmentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            // Absent or null puts the node at the root of the company chart.
            'parent_department_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'cost_center_code' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
