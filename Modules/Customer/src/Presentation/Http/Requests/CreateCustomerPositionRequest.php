<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateCustomerPositionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'decision_level' => ['sometimes', 'nullable', 'string', 'max:80'],
            // A financial ceiling in the smallest currency unit; it is a property, never an approval.
            'delegation_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
