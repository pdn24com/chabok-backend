<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Iam\Infrastructure\Persistence\Models\SessionRecord;

interface SessionRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): SessionRecord;

    public function lockByRefreshToken(string $refreshTokenHash): ?SessionRecord;

    public function lockOwnedSession(string $userId, string $sessionId): ?SessionRecord;

    /** The live session behind a refresh token, with its User, for a request that must re-authenticate. */
    public function findLiveWithActiveUser(string $refreshTokenHash, DateTimeInterface $now): ?SessionRecord;

    /** @return Collection<int, SessionRecord> */
    public function forUserNewestFirst(string $userId): Collection;

    /** @param array<string, mixed> $changes */
    public function apply(SessionRecord $session, array $changes): void;

    /** @param array<string, mixed> $changes */
    public function reviseLiveSession(string $sessionId, array $changes): void;

    /** @return list<string> */
    public function liveIdsForFamily(string $familyId): array;

    /** @return list<string> */
    public function liveIdsForUser(string $userId): array;

    /** @return list<string> */
    public function liveIdsForOtherSessions(string $userId, string $currentSessionId): array;

    /** @param list<string> $sessionIds @param array<string, mixed> $changes */
    public function reviseLiveSessions(array $sessionIds, array $changes): void;
}
