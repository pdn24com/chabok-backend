<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTeam\Domain\Enums\BulkMembershipOperation;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class RunBulkMembershipChangeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::enum(BulkMembershipOperation::class)],
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer', 'min:1', 'max:4294967295'],
            'source_team_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'target_team_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'replacement_user_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            // The run is refused while it touches open work the caller has not acknowledged.
            'confirm_open_tasks' => ['sometimes', 'boolean'],
            // The correlation is issued by the server; the replay key is the idempotency header.
            'operation_id' => ['prohibited'],
        ];
    }
}
