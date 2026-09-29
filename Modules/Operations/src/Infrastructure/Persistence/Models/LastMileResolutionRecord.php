<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class LastMileResolutionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'last_mile_resolution_evidence';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['resolution_input' => 'array'];
    }
}
