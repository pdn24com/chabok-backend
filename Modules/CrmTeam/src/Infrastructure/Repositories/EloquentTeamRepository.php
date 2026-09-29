<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

final class EloquentTeamRepository implements TeamRepositoryInterface
{
    public function activeExistsForTenant(string $hqId, string $teamId): bool
    {
        return TeamRecord::query()
            ->where(['hq_id' => $hqId, 'team_id' => $teamId, 'status' => TeamStatus::ACTIVE->value])
            ->exists();
    }

    public function listForTenant(string $hqId, ?TeamStatus $status): Collection
    {
        return TeamRecord::query()
            ->where('hq_id', $hqId)
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            // The supervisor name belongs to every row, and the head count is one grouped query.
            ->with(['supervisor' => fn ($supervisor) => $supervisor->select(['id', 'display_name'])])
            ->withCount('activeMembers')
            ->orderBy('title')
            ->get();
    }

    public function findForTenant(string $hqId, string $teamId): ?TeamRecord
    {
        return TeamRecord::query()
            ->where(['hq_id' => $hqId, 'team_id' => $teamId])
            ->with(['supervisor' => fn ($supervisor) => $supervisor->select(['id', 'display_name'])])
            ->withCount('activeMembers')
            ->first();
    }

    public function lockForTenant(string $hqId, string $teamId): ?TeamRecord
    {
        return TeamRecord::query()->where(['hq_id' => $hqId, 'team_id' => $teamId])->lockForUpdate()->first();
    }

    public function create(array $attributes): TeamRecord
    {
        return TeamRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $teamId, array $attributes): void
    {
        TeamRecord::query()->where(['hq_id' => $hqId, 'team_id' => $teamId])->update($attributes);
    }

    public function titleTaken(string $hqId, string $title, ?string $exceptTeamId = null): bool
    {
        return TeamRecord::query()
            ->where(['hq_id' => $hqId, 'title' => $title])
            ->when($exceptTeamId !== null, fn ($query) => $query->whereKeyNot($exceptTeamId))
            ->exists();
    }
}
