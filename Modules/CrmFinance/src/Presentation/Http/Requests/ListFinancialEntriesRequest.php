<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListFinancialEntriesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A customer holds few entries, so the whole ledger is returned and there is no page to ask for.
            'kind' => ['sometimes', 'nullable', Rule::enum(FinancialEntryKind::class)],
        ];
    }
}
