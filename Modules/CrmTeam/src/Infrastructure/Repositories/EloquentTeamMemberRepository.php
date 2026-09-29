<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmTeam\Application\Dto\TeamMemberFiltersDto;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

final class EloquentTeamMemberRepository implements TeamMemberRepositoryInterface
{
    public function paginateForTenant(string $hqId, TeamMemberFiltersDto $filters): LengthAwarePaginator
    {
        return TeamMemberRecord::query()
            ->where('hq_id', $hqId)
            ->when($filters->teamId !== null, fn ($query) => $query->where('team_id', $filters->teamId))
            ->when($filters->status !== null, fn ($query) => $query->where('status', $filters->status->value))
            // The person is searched by the name the operator reads and by the account they log in with.
            ->when($filters->search !== null, fn ($query) => $query->whereHas('user',
                fn ($user) => $user->whereLike('display_name', '%'.$filters->search.'%')
                    ->orWhereLike('username', '%'.$filters->search.'%')))
            ->with([
                'user' => fn ($user) => $user->select(['id', 'username', 'display_name', 'status']),
                'team' => fn ($team) => $team->select(['id', 'title', 'status']),
            ])
            ->orderByDesc('status')
            ->orderByDesc('valid_from')
            ->paginate($filters->perPage, page: $filters->page);
    }

    public function findCurrent(string $hqId, string $teamId, string $userId): ?TeamMemberRecord
    {
        return TeamMemberRecord::query()
            ->where(['hq_id' => $hqId, 'team_id' => $teamId, 'user_id' => $userId, 'status' => MembershipStatus::ACTIVE->value])
            ->first();
    }

    public function findForTenant(string $hqId, string $membershipId): ?TeamMemberRecord
    {
        return TeamMemberRecord::query()
            ->where(['hq_id' => $hqId, 'team_member_id' => $membershipId])
            ->with([
                'user' => fn ($user) => $user->select(['id', 'username', 'display_name', 'status']),
                'team' => fn ($team) => $team->select(['id', 'title', 'status']),
            ])
            ->first();
    }

    public function lockForTenant(string $hqId, string $membershipId): ?TeamMemberRecord
    {
        return TeamMemberRecord::query()
            ->where(['hq_id' => $hqId, 'team_member_id' => $membershipId])
            ->lockForUpdate()
            ->first();
    }

    public function currentForUsers(string $hqId, array $userIds, ?string $teamId = null): array
    {
        return TeamMemberRecord::query()
            ->where(['hq_id' => $hqId, 'status' => MembershipStatus::ACTIVE->value])
            ->whereIn('user_id', $userIds)
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->with(['user' => fn ($user) => $user->select(['id', 'username', 'display_name'])])
            ->get()
            ->all();
    }

    public function create(array $attributes): TeamMemberRecord
    {
        return TeamMemberRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $membershipId, array $attributes): void
    {
        TeamMemberRecord::query()->where(['hq_id' => $hqId, 'team_member_id' => $membershipId])->update($attributes);
    }
}
