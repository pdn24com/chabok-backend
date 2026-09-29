<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Every field is optional: a PATCH carries only what the operator actually changed.
            'title' => ['sometimes', 'string', 'max:200'],
            'supervisor_user_id' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
            'status' => ['sometimes', Rule::enum(TeamStatus::class)],
            'parent_team_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
