<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CityRecord extends Model
{
    protected $table = 'cities';
    protected $primaryKey = 'city_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
