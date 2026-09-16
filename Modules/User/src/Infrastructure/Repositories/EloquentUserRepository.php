<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Repositories;

use Modules\Foundation\Application\Data\Page;
use Modules\User\Infrastructure\Persistence\Models\UserRecord;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Domain\IdentifierNormalizer;

final class EloquentUserRepository implements UserRepository
{
    public function __construct(private readonly IdentifierNormalizer $normalizer)
    {
    }

    public function findById(string $userId): ?array
    {
        $row = UserRecord::query()->where('user_id', $userId)->first();
        return $row?->getAttributes();
    }

    public function findForUpdate(string $userId): ?array
    {
        return UserRecord::query()->where('user_id', $userId)->lockForUpdate()->first()?->getAttributes();
    }

    public function findTenantUserForUpdate(string $hqId, string $userId): ?array
    {
        $row = UserRecord::query()->where('hq_id', $hqId)->where('user_id', $userId)->lockForUpdate()->first();
        return $row?->getAttributes();
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $values = array_unique(array_filter([
            $this->normalizer->username($identifier),
            $this->normalizer->mobile($identifier),
            $this->normalizer->email($identifier),
        ]));
        $row = UserRecord::query()->where(function ($query) use ($values): void {
            foreach ($values as $value) {
                $query->orWhere('normalized_username', $value)->orWhere('normalized_mobile', $value)->orWhere('normalized_email', $value);
            }
        })->first();
        return $row?->getAttributes();
    }

    public function identifiersExist(array $normalizedIdentifiers): bool
    {
        $values = array_values(array_unique(array_filter($normalizedIdentifiers)));
        if ($values === []) {
            return false;
        }
        return UserRecord::query()->where(function ($query) use ($values): void {
            $query->whereIn('normalized_username', $values)->orWhereIn('normalized_mobile', $values)->orWhereIn('normalized_email', $values);
        })->exists();
    }

    public function insert(array $attributes): void
    {
        UserRecord::query()->insert($attributes);
    }

    public function update(string $userId, array $attributes): void
    {
        UserRecord::query()->where('user_id', $userId)->update($attributes);
    }

    public function paginate(
        string $hqId,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?array $visibleUserIds = null,
    ): Page
    {
        $query = UserRecord::query()->where('hq_id', $hqId)->when($visibleUserIds !== null, fn($q) => $q->whereIn('user_id', $visibleUserIds));
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($search !== null && trim($search) !== '') {
            $needle = '%' . mb_strtolower(trim($search)) . '%';
            $query->where(function ($nested) use ($needle): void {
                $nested->whereRaw('LOWER(display_name) LIKE ?', [$needle])->orWhereRaw('LOWER(username) LIKE ?', [$needle])->orWhereRaw('LOWER(email) LIKE ?', [$needle])->orWhere('mobile', 'like', $needle);
            });
        }
        $result = $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
        return new Page(array_map(fn(UserRecord $user): array => $user->getAttributes(), $result->items()), $result->currentPage(), $result->perPage(), $result->total());
    }
}
