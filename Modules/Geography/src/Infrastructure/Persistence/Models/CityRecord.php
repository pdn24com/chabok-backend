<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CityRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'cities';

    protected $guarded = ['*'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(ProvinceRecord::class, 'province_id', 'id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
