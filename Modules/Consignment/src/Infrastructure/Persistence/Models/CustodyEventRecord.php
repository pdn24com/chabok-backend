<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CustodyEventRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'parcel_custody_events';

    protected $guarded = ['*'];
}
