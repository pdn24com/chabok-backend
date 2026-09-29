<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;
use Modules\Outbox\Application\Contracts\OutboxClaimServiceInterface;
use Modules\Outbox\Application\Contracts\OutboxSettingsInterface;
use Modules\Outbox\Application\Repositories\OutboxEventRepositoryInterface;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final readonly class OutboxClaimService implements OutboxClaimServiceInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OutboxSettingsInterface $outboxSettings,
        private IdentifierGeneratorInterface $identifierGenerator,
        private OutboxEventRepositoryInterface $outboxEventRepository,
    ) {}

    /** @return list<OutboxEventRecord> */
    public function claim(int $limit, string $workerId): array
    {
        return $this->connection->transaction(function () use ($limit, $workerId) {
            $now = $this->clock->now();
            $staleBefore = $now->modify('-'.$this->outboxSettings->claimTimeout().' seconds');
            $events = $this->outboxEventRepository->lockClaimable($limit, $now, $staleBefore, $this->outboxSettings->maxAttempts());
            foreach ($events as $event) {
                $this->outboxEventRepository->apply($event, [
                    'claim_token' => $this->identifierGenerator->token(),
                    'claimed_by' => mb_substr($workerId, 0, 120),
                    'claimed_at' => $this->clock->now(),
                    'attempts' => (int) $event->attempts + 1,
                    'last_attempt_at' => $this->clock->now(),
                ]);
            }

            return $events;
        }, attempts: 1);
    }
}
