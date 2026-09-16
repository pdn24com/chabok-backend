<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListPublishedCatalogVersionsRequest extends FormRequest
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
            'include_version_ids' => ['nullable', 'array', 'max:100'],
            'include_version_ids.*' => ['uuid'],
        ];
    }
}
