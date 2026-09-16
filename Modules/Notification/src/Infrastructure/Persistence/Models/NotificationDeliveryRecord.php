<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationDeliveryRecord extends Model
{
    protected $table = 'notification_deliveries';
    protected $primaryKey = 'delivery_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
