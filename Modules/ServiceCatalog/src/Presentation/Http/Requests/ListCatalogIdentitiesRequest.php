<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListCatalogIdentitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'page_size' => ['integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
        ];
    }
}
