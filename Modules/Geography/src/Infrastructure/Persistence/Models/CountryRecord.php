<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CountryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'countries';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
