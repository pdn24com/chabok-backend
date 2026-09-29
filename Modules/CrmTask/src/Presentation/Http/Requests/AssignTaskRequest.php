<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class AssignTaskRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::enum(TaskAssignmentEventType::class)],
            'to_team_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            // An explicit null hands the task to nobody; it never means a team queue.
            'to_user_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'remind_at' => ['sometimes', 'nullable', 'date'],
            'expected_assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'operation_key' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
