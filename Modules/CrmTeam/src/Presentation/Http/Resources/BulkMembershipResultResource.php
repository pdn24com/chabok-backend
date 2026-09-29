<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTeam\Application\UseCases\RunBulkMembershipChange\RunBulkMembershipChangeResult;
use Modules\CrmTeam\Infrastructure\Persistence\Models\MembershipEventRecord;

/**
 * What a bulk run did, or would do when it was only previewed. affected_tasks is the open work of the
 * people named, so the operator sees it before the run is committed.
 *
 * @mixin RunBulkMembershipChangeResult
 */
final class BulkMembershipResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'events' => array_map(static fn (MembershipEventRecord $event): array => [
                'membership_event_id' => $event->membership_event_id,
                'membership_id' => (string) $event->membership_id,
                'event_type' => $event->event_type->value,
                'operation_id' => $event->operation_id === null ? null : (string) $event->operation_id,
            ], $this->events),
            'memberships' => TeamMemberResource::collection($this->preview->memberships)->resolve($request),
            'affected_tasks' => $this->preview->affectedTasks,
        ];
    }
}
