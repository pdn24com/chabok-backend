<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListSalesDocumentsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            // The status of the revision the document currently shows.
            'status' => ['sometimes', 'nullable', Rule::enum(SalesDocumentStatus::class)],
        ];
    }
}
