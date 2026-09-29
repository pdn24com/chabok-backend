<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateCustomerProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every field is optional: a PATCH carries only what the operator actually changed.
            'kind' => ['sometimes', Rule::enum(CustomerKind::class)],
            'phase' => ['sometimes', Rule::enum(CustomerPhase::class)],
            'lifecycle' => ['sometimes', Rule::enum(CustomerLifecycle::class)],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'customer_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'primary_industry_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
