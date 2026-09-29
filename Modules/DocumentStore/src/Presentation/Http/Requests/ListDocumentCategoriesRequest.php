<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListDocumentCategoriesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A tenant keeps few categories, so the whole tree is returned and there is no page to ask for.
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
