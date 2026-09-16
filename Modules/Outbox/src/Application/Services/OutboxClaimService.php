<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Services;

final readonly class OutboxClaimService
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Outbox\Application\Contracts\OutboxSettings $settings,
        private \Modules\Outbox\Application\Repositories\OutboxEventRepository $events,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function claim(int $limit, string $workerId): array
    {
        return $this->transactions->run(function () use ($limit, $workerId) {
            $staleBefore = $this->clock->now()->modify('-' . $this->settings->claimTimeout() . ' seconds');
            $events = $this->events->dueForPublication($limit, $this->clock->now(), $staleBefore, $this->settings->maxAttempts());
            foreach ($events as $event) {
                $claimToken = $this->identifiers->uuid();
                $this->events->update($event->event_id, [
                    'claim_token' => $claimToken,
                    'claimed_by' => mb_substr($workerId, 0, 120),
                    'claimed_at' => $this->clock->now(),
                    'attempts' => (int) $event->attempts + 1,
                    'last_attempt_at' => $this->clock->now(),
                ]);
                $event->claim_token = $claimToken;
                $event->attempts = (int) $event->attempts + 1;
            }
            return $events;
        });
    }
}
