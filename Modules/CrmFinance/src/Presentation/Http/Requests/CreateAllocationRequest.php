<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateAllocationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            // How much of the receipt is being set against the invoice; what is left is checked in the handler.
            'amount' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
        ];
    }
}
