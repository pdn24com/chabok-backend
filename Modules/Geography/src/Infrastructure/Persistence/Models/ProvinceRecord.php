<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ProvinceRecord extends Model
{
    protected $table = 'provinces';
    protected $primaryKey = 'province_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
