<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Repositories;

use Modules\Notification\Infrastructure\Persistence\Models\NotificationDeliveryRecord;

interface NotificationDeliveryRepositoryInterface
{
    public function findByEvent(string $eventId): ?NotificationDeliveryRecord;

    /**
     * Stores the receipt for an event exactly once and returns the provider message id that is now on
     * record: the inserted one, or the one a concurrent worker stored first.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordReceipt(array $attributes): string;
}
