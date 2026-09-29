<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTask\Domain\Enums\ActivityChannel;
use Modules\CrmTask\Domain\Enums\ActivityDirection;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\CrmTask\Domain\Enums\CallOutcome;
use Modules\CrmTask\Domain\Enums\MeetingMode;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

/**
 * What happened on a task, and what that does to the task, as one submission. Which detail fields
 * belong to which kind of interaction is a domain rule; only their shape is enforced here.
 */
final class RecordTaskActionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity' => ['required', 'array'],
            'activity.type' => ['required', Rule::enum(ActivityType::class)],
            'activity.occurred_at' => ['required', 'date'],
            'activity.body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'activity.result' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'activity.direction' => ['sometimes', 'nullable', Rule::enum(ActivityDirection::class)],
            'activity.contact_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'activity.contact_value' => ['sometimes', 'nullable', 'string', 'max:320'],
            'activity.channel' => ['sometimes', 'nullable', Rule::enum(ActivityChannel::class)],
            'activity.duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'activity.call_outcome' => ['sometimes', 'nullable', Rule::enum(CallOutcome::class)],
            'activity.meeting_mode' => ['sometimes', 'nullable', Rule::enum(MeetingMode::class)],
            'activity.location' => ['sometimes', 'nullable', 'string', 'max:300'],
            'activity.meeting_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'activity.document_version_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],

            // Every change beside the record is optional: an action may simply be logged.
            'task' => ['sometimes', 'array'],
            'task.status' => ['sometimes', 'nullable', Rule::enum(TaskStatus::class)],
            'task.due_at' => ['sometimes', 'nullable', 'date'],
            'task.remind_at' => ['sometimes', 'nullable', 'date'],
            'task.completion_result' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
