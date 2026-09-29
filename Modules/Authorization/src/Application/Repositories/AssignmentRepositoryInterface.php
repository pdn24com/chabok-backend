<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;

interface AssignmentRepositoryInterface
{
    public function lockForUser(string $assignmentId, string $userId): ?AssignmentRecord;

    /** @return Collection<int, AssignmentRecord> */
    public function activeForUserInTenant(string $userId, ?string $hqId): Collection;

    /** @return list<string> */
    public function activeUserIdsForRole(string $roleId): array;

    public function hasPlatformSuperAdmin(string $userId): bool;

    /** Active slots already taken among the candidates, which is how a duplicate assignment is refused. @param list<string> $activeSlots @return list<string> */
    public function occupiedSlots(array $activeSlots): array;

    /** @param list<array<string, mixed>> $rows */
    public function insert(array $rows): void;

    /** @param list<string> $activeSlots @return Collection<string, AssignmentRecord> */
    public function byActiveSlots(string $hqId, array $activeSlots): Collection;

    /** @param array<string, mixed> $changes */
    public function update(string $assignmentId, array $changes): void;
}
