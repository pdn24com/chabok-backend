<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class EndTeamMembershipRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A unix timestamp in seconds; left out, the membership ends now.
            'valid_to' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            'replacement_user_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
