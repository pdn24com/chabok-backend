<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PickupTaskRecord extends Model
{
    protected $table = 'pickup_tasks';
    protected $primaryKey = 'pickup_task_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
