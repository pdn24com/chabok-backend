<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListTeamMembersRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'status' => ['sometimes', 'nullable', Rule::enum(MembershipStatus::class)],
            // Matches the display name or the username of the person.
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
