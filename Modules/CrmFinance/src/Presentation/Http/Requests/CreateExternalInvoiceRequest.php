<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateExternalInvoiceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_system' => ['required', 'string', 'max:80'],
            'reference_no' => ['required', 'string', 'max:120'],
            // A rial amount, so it stays a whole number and an invoice is never for nothing.
            'amount' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
            // Unix timestamps in seconds; the due date is judged against the issue date in the handler.
            'issued_on' => ['required', 'integer', 'min:-2208988800', 'max:4102444800'],
            'due_on' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
