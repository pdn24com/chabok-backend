<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserStore
{
    /** @return array<string, mixed>|null */
    public function findById(string $userId): ?array;

    /** @return array<string, mixed>|null */
    public function findTenantUserForUpdate(string $hqId, string $userId): ?array;

    /** @return array<string, mixed>|null */
    public function findByIdentifier(string $identifier): ?array;

    /** @param list<string> $normalizedIdentifiers */
    public function identifiersExist(array $normalizedIdentifiers): bool;

    /** @param array<string, mixed> $attributes */
    public function insert(array $attributes): void;

    /** @param array<string, mixed> $attributes */
    public function update(string $userId, array $attributes): void;

    /** @return LengthAwarePaginator<array<string, mixed>> */
    public function paginate(
        string $hqId,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?array $visibleUserIds = null,
    ): LengthAwarePaginator;
}
