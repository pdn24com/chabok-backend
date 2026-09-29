<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListCatalogItemsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['sometimes', 'nullable', Rule::enum(CatalogItemKind::class)],
            'status' => ['sometimes', 'nullable', Rule::enum(CatalogItemStatus::class)],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
