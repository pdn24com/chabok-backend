<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Repositories;

use Illuminate\Database\QueryException;
use Modules\Notification\Application\Repositories\NotificationDeliveryRepositoryInterface;
use Modules\Notification\Infrastructure\Persistence\Models\NotificationDeliveryRecord;

final class EloquentNotificationDeliveryRepository implements NotificationDeliveryRepositoryInterface
{
    /** SQLSTATE raised when the unique receipt index rejects a concurrent insert. */
    private const INTEGRITY_VIOLATION = '23000';

    public function findByEvent(string $eventId): ?NotificationDeliveryRecord
    {
        return NotificationDeliveryRecord::query()->where('event_id', $eventId)->first();
    }

    public function recordReceipt(array $attributes): string
    {
        try {
            NotificationDeliveryRecord::query()->forceCreate($attributes);
        } catch (QueryException $error) {
            if ($error->getCode() !== self::INTEGRITY_VIOLATION) {
                throw $error;
            }
            $existing = $this->findByEvent((string) $attributes['event_id']);
            if ($existing === null) {
                throw $error;
            }

            return (string) $existing->provider_message_id;
        }

        return (string) $attributes['provider_message_id'];
    }
}
