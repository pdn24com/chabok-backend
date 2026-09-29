<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateCatalogItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every field is optional: a PATCH carries only what the operator actually changed. The
            // code, title, kind and status cannot be cleared; the rest may be sent as an explicit null.
            'code' => ['sometimes', 'string', 'max:80'],
            'title' => ['sometimes', 'string', 'max:200'],
            'kind' => ['sometimes', Rule::enum(CatalogItemKind::class)],
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
            // A list, even an empty one, replaces the whole set of industries.
            'industry_ids' => ['sometimes', 'array', 'max:100'],
            'industry_ids.*' => ['integer', 'min:1', 'max:4294967295'],
        ];
    }
}
