<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListCustomersRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'customer_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phase' => ['sometimes', 'nullable', Rule::enum(CustomerPhase::class)],
            'kind' => ['sometimes', 'nullable', Rule::enum(CustomerKind::class)],
            'lifecycle' => ['sometimes', 'nullable', Rule::enum(CustomerLifecycle::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'updated_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'updated_at_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'updated_at_to' => [
                'sometimes', 'nullable', 'date_format:Y-m-d',
                Rule::when($this->filled('updated_at_from'), 'after_or_equal:updated_at_from'),
            ],
        ];
    }
}
