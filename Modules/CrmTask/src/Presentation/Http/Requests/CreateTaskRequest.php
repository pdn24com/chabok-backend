<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTask\Domain\Enums\TaskPriority;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CreateTaskRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            // The reminder-before-deadline rule is a domain rule; the shape alone is enforced here.
            'due_at' => ['sometimes', 'nullable', 'date'],
            'remind_at' => ['sometimes', 'nullable', 'date'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'team_context_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'operation_key' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
