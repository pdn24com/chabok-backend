<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskAssignmentEventRecord;

/** @mixin TaskAssignmentEventRecord */
final class TaskAssignmentEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'task_assignment_event_id' => $this->task_assignment_event_id,
            'event_type' => $this->event_type->value,
            'from_user_id' => $this->from_user_id,
            'to_user_id' => $this->to_user_id,
            'to_team_id' => $this->to_team_id,
            'reason' => $this->reason,
            'occurred_at' => $this->occurred_at?->toISOString(),
        ];
    }
}
