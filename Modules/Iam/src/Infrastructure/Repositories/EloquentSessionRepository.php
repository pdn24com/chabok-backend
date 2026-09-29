<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\SessionRecord;

final class EloquentSessionRepository implements SessionRepositoryInterface
{
    public function create(array $attributes): SessionRecord
    {
        return SessionRecord::query()->forceCreate($attributes);
    }

    public function lockByRefreshToken(string $refreshTokenHash): ?SessionRecord
    {
        return SessionRecord::query()->where('refresh_token_hash', $refreshTokenHash)->lockForUpdate()->first();
    }

    public function lockOwnedSession(string $userId, string $sessionId): ?SessionRecord
    {
        return SessionRecord::query()->where('user_id', $userId)->where('session_id', $sessionId)->lockForUpdate()->first();
    }

    public function findLiveWithActiveUser(string $refreshTokenHash, DateTimeInterface $now): ?SessionRecord
    {
        return SessionRecord::query()->with('user')->where('refresh_token_hash', $refreshTokenHash)->whereNull('revoked_at')
            ->where('expires_at', '>', $now)->whereHas('user', fn ($user) => $user->where('status', 'ACTIVE'))->first();
    }

    public function forUserNewestFirst(string $userId): Collection
    {
        return SessionRecord::query()->where('user_id', $userId)->orderByDesc('issued_at')->get();
    }

    public function apply(SessionRecord $session, array $changes): void
    {
        $session->forceFill($changes)->save();
    }

    public function reviseLiveSession(string $sessionId, array $changes): void
    {
        SessionRecord::query()->where('session_id', $sessionId)->whereNull('revoked_at')->update($changes);
    }

    public function liveIdsForFamily(string $familyId): array
    {
        return SessionRecord::query()->where('token_family_id', $familyId)->pluck('session_id')->all();
    }

    public function liveIdsForUser(string $userId): array
    {
        return SessionRecord::query()->where('user_id', $userId)->whereNull('revoked_at')->pluck('session_id')->all();
    }

    public function liveIdsForOtherSessions(string $userId, string $currentSessionId): array
    {
        return SessionRecord::query()->where('user_id', $userId)->where('session_id', '!=', $currentSessionId)
            ->whereNull('revoked_at')->pluck('session_id')->all();
    }

    public function reviseLiveSessions(array $sessionIds, array $changes): void
    {
        SessionRecord::query()->whereIn('session_id', $sessionIds)->whereNull('revoked_at')->update($changes);
    }
}
