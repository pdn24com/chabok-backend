<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class NotificationDeliveryRecord extends Model
{
    use HasNumericIdentity;

    public const UPDATED_AT = null;

    protected $table = 'notification_deliveries';

    protected $guarded = ['*'];
}
