<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListManifestCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }
}
