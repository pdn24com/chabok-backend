<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class NodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'in:ACTIVE,INACTIVE'],
            'area_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'node_type' => ['sometimes', 'nullable', 'in:BRANCH,HUB,GATEWAY,AGENT'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
