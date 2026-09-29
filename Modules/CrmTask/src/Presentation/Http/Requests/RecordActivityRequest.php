<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTask\Domain\Enums\ActivityChannel;
use Modules\CrmTask\Domain\Enums\ActivityDirection;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\CrmTask\Domain\Enums\CallOutcome;
use Modules\CrmTask\Domain\Enums\MeetingMode;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

/**
 * An interaction recorded on its own. Which detail fields belong to which type, that a referral cannot
 * be entered by hand and that it must be filed somewhere are domain rules; only the shape is enforced here.
 */
final class RecordActivityRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ActivityType::class)],
            'occurred_at' => ['required', 'date'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'task_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'contact_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'result' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'direction' => ['sometimes', 'nullable', Rule::enum(ActivityDirection::class)],
            'channel' => ['sometimes', 'nullable', Rule::enum(ActivityChannel::class)],
            'contact_value' => ['sometimes', 'nullable', 'string', 'max:320'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'call_outcome' => ['sometimes', 'nullable', Rule::enum(CallOutcome::class)],
            'meeting_mode' => ['sometimes', 'nullable', Rule::enum(MeetingMode::class)],
            'meeting_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'location' => ['sometimes', 'nullable', 'string', 'max:300'],
            'document_version_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'participants' => ['sometimes', 'nullable', 'array', 'max:100'],
            'participants.*' => ['array'],
            'participants.*.user_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'participants.*.minutes' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
