<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateCustomerPositionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'decision_level' => ['sometimes', 'nullable', 'string', 'max:80'],
            'delegation_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
