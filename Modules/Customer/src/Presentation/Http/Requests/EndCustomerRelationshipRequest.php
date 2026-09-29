<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class EndCustomerRelationshipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The end date is always stated; the server never guesses "today".
        return ['valid_to' => ['required', 'date_format:Y-m-d']];
    }
}
