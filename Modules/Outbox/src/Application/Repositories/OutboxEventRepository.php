<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Repositories;

interface OutboxEventRepository
{
    /** @return list<object> Rows locked by the caller's transaction. */

    public function dueForPublication(int $limit, \DateTimeImmutable $now, \DateTimeImmutable $staleBefore, int $maxAttempts): array;

    public function lockEvent(string $eventId): ?object;

    public function lockClaim(string $eventId, string $claimToken): ?object;
    /** @return list<array<string, mixed>> Safe lifecycle metadata only. */

    public function inspect(?string $state, int $limit): array;

    public function update(string $eventId, array $attributes): void;
}
