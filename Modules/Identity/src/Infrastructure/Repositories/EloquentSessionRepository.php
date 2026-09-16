<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Repositories;

use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Infrastructure\Persistence\Models\SessionRecord;

final class EloquentSessionRepository implements SessionRepository
{
    public function activePrincipalForRefresh(string $hash, \DateTimeInterface $at): ?object
    {
        return SessionRecord::query()->toBase()->from('user_sessions as s')->join('users as u', 'u.user_id', '=', 's.user_id')->where('s.refresh_token_hash', $hash)->whereNull('s.revoked_at')->where('s.expires_at', '>', $at)->where('u.status', 'ACTIVE')->select(['s.session_id', 's.user_id', 's.hq_id', 'u.must_change_password'])->first();
    }

    public function findByRefreshHashForUpdate(string $hash): ?\stdClass
    {
        $row = SessionRecord::query()->where('refresh_token_hash', $hash)->lockForUpdate()->first();
        return $row === null ? null : (object) $row->getAttributes();
    }

    public function findOwnedForUpdate(string $userId, string $sessionId): ?\stdClass
    {
        $row = SessionRecord::query()->where('session_id', $sessionId)->where('user_id', $userId)->lockForUpdate()->first();
        return $row === null ? null : (object) $row->getAttributes();
    }

    public function insert(array $attributes): void
    {
        SessionRecord::query()->insert($attributes);
    }

    public function update(string $sessionId, array $attributes): void
    {
        SessionRecord::query()->where('session_id', $sessionId)->update($attributes);
    }

    public function updateActive(string $sessionId, array $attributes): void
    {
        SessionRecord::query()->where('session_id', $sessionId)->whereNull('revoked_at')->update($attributes);
    }

    public function activeForUser(string $userId, ?string $exceptSessionId = null): array
    {
        $query = SessionRecord::query()->where('user_id', $userId)->whereNull('revoked_at');
        if ($exceptSessionId !== null) {
            $query->where('session_id', '<>', $exceptSessionId);
        }
        return $query->get()->map(fn(SessionRecord $row): \stdClass => (object) $row->getAttributes())->all();
    }

    public function allForUser(string $userId): array
    {
        return SessionRecord::query()->where('user_id', $userId)->orderByDesc('issued_at')->get()->map(fn(SessionRecord $row): \stdClass => (object) $row->getAttributes())->all();
    }

    public function allForFamily(string $familyId): array
    {
        return SessionRecord::query()->where('token_family_id', $familyId)->get()->map(fn(SessionRecord $row): \stdClass => (object) $row->getAttributes())->all();
    }
}
