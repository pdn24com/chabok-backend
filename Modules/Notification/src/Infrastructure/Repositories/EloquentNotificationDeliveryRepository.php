<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Modules\Notification\Application\Repositories\NotificationDeliveryRepository;
use Modules\Notification\Infrastructure\Persistence\Models\NotificationDeliveryRecord;

final class EloquentNotificationDeliveryRepository implements NotificationDeliveryRepository
{
    public function find(string $eventId): ?object
    {
        return NotificationDeliveryRecord::query()->toBase()->where('event_id', $eventId)->first();
    }

    public function challengeContext(string $challengeId): ?object
    {
        return DB::table('otp_challenges as o')->leftJoin('users as u', 'u.user_id', '=', 'o.user_id')->where('o.challenge_id', $challengeId)->first(['o.destination_fingerprint', 'o.purpose', 'u.normalized_mobile', 'u.normalized_email']);
    }

    public function recordReceipt(array $attributes): string
    {
        try {
            NotificationDeliveryRecord::query()->toBase()->insert($attributes);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            $existing = $this->find($attributes['event_id']);
            if ($existing === null) {
                throw $error;
            }
            return (string) $existing->provider_message_id;
        }
        return $attributes['provider_message_id'];
    }
}
