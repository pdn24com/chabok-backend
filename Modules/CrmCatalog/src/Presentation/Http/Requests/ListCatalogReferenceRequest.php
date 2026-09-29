<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

/** The filter shared by the category, persona and sales model lists the item form fills its selects from. */
final class ListCatalogReferenceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
