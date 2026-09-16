<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CustodyEventRecord extends Model
{
    protected $table = 'parcel_custody_events';
    protected $primaryKey = 'custody_event_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
