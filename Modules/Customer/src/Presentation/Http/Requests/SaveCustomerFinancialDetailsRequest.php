<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Customer\Domain\Enums\CreditRating;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class SaveCustomerFinancialDetailsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every figure below is typed in by hand, so the day it describes and where it came from are
            // demanded with it; a summary nobody can date or source is worse than no summary at all.
            'financial_reference_date' => ['required', 'integer', 'min:-2208988800', 'max:4102444800'],
            'source_note' => ['required', 'string', 'max:2000'],
            // A warning threshold, not a block: rial amounts stay whole and never go negative.
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            'credit_rating' => ['nullable', Rule::enum(CreditRating::class)],
            'settlement_terms' => ['nullable', 'string', 'max:2000'],
            'accounting_code' => ['nullable', 'string', 'max:80'],
            'accounting_title' => ['nullable', 'string', 'max:200'],
            // A balance may stand on either side of the account, so these four are signed.
            'revenue' => ['nullable', 'integer'],
            'receipts' => ['nullable', 'integer'],
            'direct_cost' => ['nullable', 'integer'],
            'balance' => ['nullable', 'integer'],
        ];
    }
}
