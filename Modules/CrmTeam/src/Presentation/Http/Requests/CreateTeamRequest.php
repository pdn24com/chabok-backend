<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            // The supervisor is enrolled in the team by the same call, so the column is never left empty.
            'supervisor_user_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'status' => ['sometimes', Rule::enum(TeamStatus::class)],
            'parent_team_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
