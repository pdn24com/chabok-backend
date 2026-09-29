<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateFinancialEntryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(FinancialEntryKind::class)],
            // A reversal carries the negative of what it undoes, so the amount is signed but never zero.
            'amount' => ['required', 'integer', 'not_in:0'],
            // A unix timestamp in seconds: the day the money moved, not the day it was typed in.
            'effective_on' => ['required', 'integer', 'min:-2208988800', 'max:4102444800'],
            // Where the figure came from: a receipt number, a statement line, a spreadsheet cell.
            'source_ref' => ['required', 'string', 'max:120'],
            'invoice_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'reverses_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
