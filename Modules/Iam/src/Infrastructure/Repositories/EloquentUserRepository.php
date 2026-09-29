<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Iam\Application\Dto\UserSearchDto;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(string $userId): ?UserRecord
    {
        return UserRecord::query()->where('user_id', $userId)->first();
    }

    public function findByTenant(string $hqId, string $userId): ?UserRecord
    {
        return UserRecord::query()->where(['hq_id' => $hqId, 'user_id' => $userId])->first();
    }

    public function existsInTenant(string $hqId, string $userId): bool
    {
        return UserRecord::query()->where(['hq_id' => $hqId, 'user_id' => $userId])->exists();
    }

    public function lock(string $userId): ?UserRecord
    {
        return UserRecord::query()->where('user_id', $userId)->lockForUpdate()->first();
    }

    public function lockByTenant(string $hqId, string $userId): ?UserRecord
    {
        return UserRecord::query()->where(['hq_id' => $hqId, 'user_id' => $userId])->lockForUpdate()->first();
    }

    public function findByNormalizedIdentifiers(array $normalizedIdentifiers): ?UserRecord
    {
        return $this->matchingIdentifiers($normalizedIdentifiers)->first();
    }

    public function normalizedIdentifiersExist(array $normalizedIdentifiers): bool
    {
        return $this->matchingIdentifiers($normalizedIdentifiers)->exists();
    }

    public function idByNormalizedUsername(string $normalizedUsername): ?string
    {
        $id = UserRecord::query()->where('normalized_username', $normalizedUsername)->value('user_id');

        return $id === null ? null : (string) $id;
    }

    public function idByTenantUsername(string $hqId, string $normalizedUsername): ?string
    {
        $id = UserRecord::query()->where('hq_id', $hqId)->where('normalized_username', $normalizedUsername)->value('user_id');

        return $id === null ? null : (string) $id;
    }

    public function idsByTenant(string $hqId): array
    {
        return UserRecord::query()->where('hq_id', $hqId)->pluck('user_id')->all();
    }

    public function search(string $hqId, ?array $visibleUserIds, UserSearchDto $filters): LengthAwarePaginator
    {
        $query = UserRecord::query()->where('hq_id', $hqId)->when($visibleUserIds !== null, fn ($users) => $users->whereIn('user_id', $visibleUserIds));
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }
        if ($filters->search !== null && trim($filters->search) !== '') {
            $needle = '%'.mb_strtolower(trim($filters->search)).'%';
            $query->where(fn ($users) => $users->whereRaw('LOWER(display_name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(username) LIKE ?', [$needle])->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                ->orWhere('mobile', 'like', $needle));
        }

        return $query->orderByDesc('created_at')->paginate($filters->pageSize, ['*'], 'page', $filters->page);
    }

    public function create(array $attributes): UserRecord
    {
        return UserRecord::query()->forceCreate($attributes);
    }

    public function apply(UserRecord $user, array $changes): void
    {
        $user->forceFill($changes)->save();
    }

    public function update(string $userId, array $changes): void
    {
        UserRecord::query()->where('user_id', $userId)->update($changes);
    }

    /** @param list<string> $values */
    private function matchingIdentifiers(array $values): Builder
    {
        return UserRecord::query()->where(fn ($query) => $query->whereIn('normalized_username', $values)
            ->orWhereIn('normalized_mobile', $values)->orWhereIn('normalized_email', $values));
    }
}
