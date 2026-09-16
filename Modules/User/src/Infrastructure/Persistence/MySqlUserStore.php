<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\User\Application\Contracts\UserStore;
use Modules\User\Domain\IdentifierNormalizer;

final class MySqlUserStore implements UserStore
{
    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    public function findById(string $userId): ?array
    {
        $row = DB::table('users')->where('user_id', $userId)->first();

        return $row === null ? null : (array) $row;
    }

    public function findTenantUserForUpdate(string $hqId, string $userId): ?array
    {
        $row = DB::table('users')
            ->where('hq_id', $hqId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : (array) $row;
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $values = array_unique(array_filter([
            $this->normalizer->username($identifier),
            $this->normalizer->mobile($identifier),
            $this->normalizer->email($identifier),
        ]));

        $row = DB::table('users')->where(function ($query) use ($values): void {
            foreach ($values as $value) {
                $query->orWhere('normalized_username', $value)
                    ->orWhere('normalized_mobile', $value)
                    ->orWhere('normalized_email', $value);
            }
        })->first();

        return $row === null ? null : (array) $row;
    }

    public function identifiersExist(array $normalizedIdentifiers): bool
    {
        $values = array_values(array_unique(array_filter($normalizedIdentifiers)));
        if ($values === []) {
            return false;
        }

        return DB::table('users')->where(function ($query) use ($values): void {
            $query->whereIn('normalized_username', $values)
                ->orWhereIn('normalized_mobile', $values)
                ->orWhereIn('normalized_email', $values);
        })->exists();
    }

    public function insert(array $attributes): void
    {
        DB::table('users')->insert($attributes);
    }

    public function update(string $userId, array $attributes): void
    {
        DB::table('users')->where('user_id', $userId)->update($attributes);
    }

    public function paginate(
        string $hqId,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?array $visibleUserIds = null,
    ): LengthAwarePaginator {
        $query = DB::table('users')->where('hq_id', $hqId)->when($visibleUserIds !== null, fn ($q) => $q->whereIn('user_id', $visibleUserIds));

        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($search !== null && trim($search) !== '') {
            $needle = '%'.mb_strtolower(trim($search)).'%';
            $query->where(function ($nested) use ($needle): void {
                $nested->whereRaw('LOWER(display_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhere('mobile', 'like', $needle);
            });
        }

        return $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }
}
