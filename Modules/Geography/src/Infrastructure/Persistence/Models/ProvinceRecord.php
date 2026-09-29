<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ProvinceRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'provinces';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }
}
