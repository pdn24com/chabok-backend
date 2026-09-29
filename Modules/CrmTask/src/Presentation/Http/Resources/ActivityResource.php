<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityRecord;

/**
 * One recorded interaction. The detail fields of the other kinds stay null rather than absent.
 *
 * @mixin ActivityRecord
 */
final class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'activity_id' => $this->activity_id,
            'type' => $this->type,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'body' => $this->body,
            'result' => $this->result,
            'direction' => $this->direction,
            'channel' => $this->channel,
            'contact_customer_id' => $this->contact_customer_id,
            'contact_value' => $this->contact_value,
            'duration_minutes' => $this->duration_minutes === null ? null : (int) $this->duration_minutes,
            'call_outcome' => $this->call_outcome,
            'meeting_mode' => $this->meeting_mode,
            'location' => $this->location,
            'meeting_url' => $this->meeting_url,
            'document_version_id' => $this->document_version_id,
        ];
    }
}
