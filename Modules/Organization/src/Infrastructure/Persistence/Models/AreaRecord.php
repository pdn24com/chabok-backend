<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AreaRecord extends Model
{
    protected $table = 'areas';
    protected $primaryKey = 'area_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
