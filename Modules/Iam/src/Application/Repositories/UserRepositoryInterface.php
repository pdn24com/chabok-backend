<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Iam\Application\Dto\UserSearchDto;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

interface UserRepositoryInterface
{
    public function find(string $userId): ?UserRecord;

    public function findByTenant(string $hqId, string $userId): ?UserRecord;

    public function existsInTenant(string $hqId, string $userId): bool;

    public function lock(string $userId): ?UserRecord;

    public function lockByTenant(string $hqId, string $userId): ?UserRecord;

    /**
     * Resolves a User from any of its normalized identifiers in one read, so a sign-in never probes
     * username, mobile and e-mail separately.
     *
     * @param  list<string>  $normalizedIdentifiers
     */
    public function findByNormalizedIdentifiers(array $normalizedIdentifiers): ?UserRecord;

    /** @param list<string> $normalizedIdentifiers */
    public function normalizedIdentifiersExist(array $normalizedIdentifiers): bool;

    public function idByNormalizedUsername(string $normalizedUsername): ?string;

    public function idByTenantUsername(string $hqId, string $normalizedUsername): ?string;

    /** @return list<string> */
    public function idsByTenant(string $hqId): array;

    /** @param list<string>|null $visibleUserIds @return LengthAwarePaginator<UserRecord> */
    public function search(string $hqId, ?array $visibleUserIds, UserSearchDto $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): UserRecord;

    /** @param array<string, mixed> $changes */
    public function apply(UserRecord $user, array $changes): void;

    /** @param array<string, mixed> $changes */
    public function update(string $userId, array $changes): void;
}
