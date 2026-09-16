<?php

declare(strict_types=1);

namespace Modules\User\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface UserRepository
{
    /** @return array<string, mixed>|null */

    public function findById(string $userId): ?array;

    public function findForUpdate(string $userId): ?array;
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
    /** @return Page<array<string, mixed>> */

    public function paginate(
        string $hqId,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?array $visibleUserIds = null,
    ): Page;
}
