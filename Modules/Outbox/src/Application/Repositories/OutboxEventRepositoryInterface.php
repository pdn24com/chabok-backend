<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

interface OutboxEventRepositoryInterface
{
    /**
     * Locks the next publishable batch and skips rows another worker already holds, so concurrent
     * workers never contend for the same event.
     *
     * @return list<OutboxEventRecord>
     */
    public function lockClaimable(int $limit, DateTimeInterface $now, DateTimeInterface $staleBefore, int $maxAttempts): array;

    public function lockByClaim(string $eventId, ?string $claimToken): ?OutboxEventRecord;

    public function lockById(string $eventId): ?OutboxEventRecord;

    /** @param array<string, mixed> $changes */
    public function apply(OutboxEventRecord $event, array $changes): void;

    /** @return Collection<int, OutboxEventRecord> */
    public function recentSummaries(?string $state, int $limit): Collection;
}
