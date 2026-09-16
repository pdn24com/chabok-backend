<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class NumberRangeRecord extends Model
{
    protected $table = 'consignment_number_ranges';
    protected $primaryKey = 'range_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
