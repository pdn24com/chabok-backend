<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Repositories;

use Modules\Outbox\Application\Repositories\OutboxEventRepository;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final class EloquentOutboxEventRepository implements OutboxEventRepository
{
    public function dueForPublication(int $limit, \DateTimeImmutable $now, \DateTimeImmutable $staleBefore, int $maxAttempts): array
    {
        return OutboxEventRecord::query()->toBase()->whereIn('publication_state', ['PENDING', 'FAILED'])->where(fn($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))->where(fn($q) => $q->whereNull('claim_token')->orWhere('claimed_at', '<=', $staleBefore))->where('attempts', '<', $maxAttempts)->orderBy('occurred_at')->limit($limit)->lock('FOR UPDATE SKIP LOCKED')->get()->all();
    }

    public function lockEvent(string $eventId): ?object
    {
        return OutboxEventRecord::query()->toBase()->where('event_id', $eventId)->lockForUpdate()->first();
    }

    public function lockClaim(string $eventId, string $claimToken): ?object
    {
        return OutboxEventRecord::query()->toBase()->where(['event_id' => $eventId, 'claim_token' => $claimToken])->lockForUpdate()->first();
    }

    public function update(string $eventId, array $attributes): void
    {
        OutboxEventRecord::query()->where('event_id', $eventId)->update($attributes);
    }

    public function inspect(?string $state, int $limit): array
    {
        return OutboxEventRecord::query()->toBase()->when($state, fn($query, $state) => $query->where('publication_state', $state))->orderByDesc('occurred_at')->limit($limit)->get([
            'event_id',
            'hq_id',
            'event_type',
            'publication_state',
            'attempts',
            'next_attempt_at',
            'claimed_by',
            'claimed_at',
            'last_failure_code',
            'published_at',
            'dead_lettered_at',
            'correlation_id',
        ])->map(fn($row) => (array) $row)->all();
    }
}
