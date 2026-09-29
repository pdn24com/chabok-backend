<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateSalesDocumentVersionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Everything is optional: an empty body restates the superseded revision unchanged.
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/D'],
            'total' => ['sometimes', 'integer', 'min:0', 'max:9223372036854775807'],
            'expires_at' => ['sometimes', 'date'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }
}
