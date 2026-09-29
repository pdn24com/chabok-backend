<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

/**
 * One membership. The CRM role of the person is not here: it lives in IAM, and this row records only
 * which team they are currently in and for how long.
 *
 * @mixin TeamMemberRecord
 */
final class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'membership_id' => $this->team_member_id,
            'team_id' => (string) $this->team_id,
            'team' => $this->team === null ? null : ['team_id' => $this->team->team_id, 'title' => $this->team->title],
            'user' => $this->user === null ? null : [
                'user_id' => $this->user->user_id,
                'username' => $this->user->username,
                'display_name' => $this->user->display_name,
            ],
            'status' => $this->status->value,
            'valid_from' => $this->valid_from?->toISOString(),
            'valid_to' => $this->valid_to?->toISOString(),
        ];
    }
}
