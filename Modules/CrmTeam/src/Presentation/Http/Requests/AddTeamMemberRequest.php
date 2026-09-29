<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class AddTeamMemberRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // An existing Chabok user; creating an account is IAM's job, not this one's.
            'user_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            // A unix timestamp in seconds; left out, the membership starts now.
            'valid_from' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            // The CRM role lives in IAM, so it is refused here rather than silently ignored.
            'role' => ['prohibited'],
        ];
    }
}
