<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateCatalogItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::enum(CatalogItemKind::class)],
            'status' => ['sometimes', Rule::enum(CatalogItemStatus::class)],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'buyer_persona_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'sales_model_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'delivery_terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'lead_time' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'after_sales_policy' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sla_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'warranty_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'legal_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'industry_ids' => ['sometimes', 'nullable', 'array', 'max:100'],
            'industry_ids.*' => ['integer', 'min:1', 'max:4294967295'],
        ];
    }
}
