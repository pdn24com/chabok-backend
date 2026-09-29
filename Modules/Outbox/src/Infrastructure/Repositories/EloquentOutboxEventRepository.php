<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Outbox\Application\Repositories\OutboxEventRepositoryInterface;
use Modules\Outbox\Domain\Enums\PublicationState;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final class EloquentOutboxEventRepository implements OutboxEventRepositoryInterface
{
    /** Columns a dead-letter inspection needs; the payload stays out of the listing on purpose. */
    private const SUMMARY_COLUMNS = ['event_id', 'hq_id', 'event_type', 'publication_state', 'attempts', 'next_attempt_at',
        'claimed_by', 'claimed_at', 'last_failure_code', 'published_at', 'dead_lettered_at', 'correlation_id'];

    public function lockClaimable(int $limit, DateTimeInterface $now, DateTimeInterface $staleBefore, int $maxAttempts): array
    {
        return OutboxEventRecord::query()
            ->whereIn('publication_state', [PublicationState::PENDING, PublicationState::FAILED])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('claim_token')->orWhere('claimed_at', '<=', $staleBefore))
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('occurred_at')->limit($limit)
            ->lock('FOR UPDATE SKIP LOCKED')->get()->all();
    }

    public function lockByClaim(string $eventId, ?string $claimToken): ?OutboxEventRecord
    {
        return OutboxEventRecord::query()->where('event_id', $eventId)->where('claim_token', $claimToken)->lockForUpdate()->first();
    }

    public function lockById(string $eventId): ?OutboxEventRecord
    {
        return OutboxEventRecord::query()->where('event_id', $eventId)->lockForUpdate()->first();
    }

    public function apply(OutboxEventRecord $event, array $changes): void
    {
        $event->forceFill($changes)->save();
    }

    public function recentSummaries(?string $state, int $limit): Collection
    {
        return OutboxEventRecord::query()
            ->when($state, fn ($query) => $query->where('publication_state', strtoupper($state)))
            ->orderByDesc('occurred_at')->limit($limit)
            ->get(self::SUMMARY_COLUMNS);
    }
}
