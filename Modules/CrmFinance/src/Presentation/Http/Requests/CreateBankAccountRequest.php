<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmFinance\Domain\Enums\BankAccountStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateBankAccountRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::enum(BankAccountStatus::class)],
            // An Iranian IBAN is IR followed by twenty-four digits. Which of the three numbers must be
            // there is a domain rule, because any one of them is enough to pay into the account.
            'iban' => ['sometimes', 'nullable', 'string', 'regex:/^IR\d{24}$/D'],
            'card_number' => ['sometimes', 'nullable', 'string', 'regex:/^\d{16}$/D'],
            'account_no' => ['sometimes', 'nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Operators paste these numbers off statements and cards, where they carry spaces and dashes.
        foreach (['iban', 'card_number', 'account_no'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/[\s\-]+/u', '', $this->input($field))]);
            }
        }
        if (is_string($this->input('iban'))) {
            $this->merge(['iban' => strtoupper($this->input('iban'))]);
        }
    }
}
