<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class DeliveryTaskRecord extends Model
{
    protected $table = 'delivery_tasks';
    protected $primaryKey = 'delivery_task_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
