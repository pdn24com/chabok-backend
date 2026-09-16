<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Repositories;

interface SessionRepository
{
    public function activePrincipalForRefresh(string $hash, \DateTimeInterface $at): ?object;

    public function findByRefreshHashForUpdate(string $hash): ?\stdClass;

    public function findOwnedForUpdate(string $userId, string $sessionId): ?\stdClass;

    public function insert(array $attributes): void;

    public function update(string $sessionId, array $attributes): void;

    public function updateActive(string $sessionId, array $attributes): void;

    public function activeForUser(string $userId, ?string $exceptSessionId = null): array;

    public function allForUser(string $userId): array;

    public function allForFamily(string $familyId): array;
}
