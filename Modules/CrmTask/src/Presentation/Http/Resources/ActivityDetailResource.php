<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityParticipantRecord;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityRecord;

/**
 * An interaction with everything it is filed against and everyone who took part. The lighter
 * ActivityResource stays what the task action answers with.
 *
 * @mixin ActivityRecord
 */
final class ActivityDetailResource extends JsonResource
{
    /** @param list<ActivityParticipantRecord> $participants */
    public function __construct(ActivityRecord $resource, private readonly array $participants)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'activity_id' => $this->activity_id,
            'type' => $this->type,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'customer_id' => $this->customer_id,
            'opportunity_id' => $this->opportunity_id,
            'task_id' => $this->task_id,
            'contact_customer_id' => $this->contact_customer_id,
            'body' => $this->body,
            'result' => $this->result,
            'direction' => $this->direction,
            'channel' => $this->channel,
            'contact_value' => $this->contact_value,
            'duration_minutes' => $this->duration_minutes === null ? null : (int) $this->duration_minutes,
            'call_outcome' => $this->call_outcome,
            'meeting_mode' => $this->meeting_mode,
            'meeting_url' => $this->meeting_url,
            'location' => $this->location,
            'document_version_id' => $this->document_version_id,
            'participants' => array_map(
                static fn (ActivityParticipantRecord $participant): array => [
                    'user_id' => $participant->user_id,
                    'minutes' => (int) $participant->minutes,
                ],
                $this->participants,
            ),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
