<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ChangeCustomerLifecycleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lifecycle' => ['required', Rule::enum(CustomerLifecycle::class)],
            // Required to close or archive a record, optional to reopen it.
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('lifecycle'), [CustomerLifecycle::INACTIVE->value, CustomerLifecycle::ARCHIVED->value], true)),
                'nullable', 'string', 'max:1000',
            ],
        ];
    }
}
