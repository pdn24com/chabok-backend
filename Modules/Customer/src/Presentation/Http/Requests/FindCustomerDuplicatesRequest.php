<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class FindCustomerDuplicatesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Either one is enough; an empty query parameter counts as absent. Whether the mobile number is a
            // usable one is judged by the handler with the same value object the write paths use.
            'mobile' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'email' => ['nullable', 'string', 'max:320', 'email', 'required_without:mobile'],
        ];
    }
}
