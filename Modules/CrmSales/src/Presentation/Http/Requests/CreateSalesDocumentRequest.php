<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmSales\Domain\Enums\SalesDocumentType;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateSalesDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The customer is not accepted here: it comes from the opportunity the document is drawn against.
            'opportunity_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'document_type' => ['required', Rule::enum(SalesDocumentType::class)],
            // Null or absent asks the server for the next number of this type and year.
            'document_no' => ['sometimes', 'nullable', 'string', 'max:80'],
            'version' => ['required', 'array:currency,total,expires_at,terms'],
            'version.currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/D'],
            // Rial amounts have no minor unit, so the total is a whole number and never negative.
            'version.total' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            'version.expires_at' => ['required', 'date'],
            'version.terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('version.currency'))) {
            $this->merge(['version' => [...$this->input('version'), 'currency' => strtoupper(trim($this->input('version.currency')))]]);
        }
    }
}
