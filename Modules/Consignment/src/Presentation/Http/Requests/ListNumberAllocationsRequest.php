<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListNumberAllocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'page_size' => ['sometimes', 'integer', 'min:1', 'max:250']];
    }
}
