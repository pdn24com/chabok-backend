<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Repositories;

interface NotificationDeliveryRepository
{
    public function find(string $eventId): ?object;

    public function challengeContext(string $challengeId): ?object;

    public function recordReceipt(array $attributes): string;
}
