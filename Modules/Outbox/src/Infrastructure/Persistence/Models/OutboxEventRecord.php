<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OutboxEventRecord extends Model
{
    protected $table = 'outbox_events';
    protected $primaryKey = 'event_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
