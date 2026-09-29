<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

/** @mixin TeamRecord */
final class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'team_id' => $this->team_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'parent_team_id' => $this->parent_team_id === null ? null : (string) $this->parent_team_id,
            'supervisor' => $this->supervisor === null ? null
                : ['user_id' => $this->supervisor->user_id, 'display_name' => $this->supervisor->display_name],
            'member_count' => (int) ($this->active_members_count ?? 0),
        ];
    }
}
