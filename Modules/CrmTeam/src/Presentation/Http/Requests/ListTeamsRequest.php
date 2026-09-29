<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListTeamsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A tenant keeps a handful of teams, so the whole set is returned and there is no page to ask for.
        return ['status' => ['sometimes', 'nullable', Rule::enum(TeamStatus::class)]];
    }
}
