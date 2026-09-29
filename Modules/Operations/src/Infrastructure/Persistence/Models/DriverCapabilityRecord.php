<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Operations\Domain\Enums\DriverCapability;

final class DriverCapabilityRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'driver_capabilities';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['capability' => DriverCapability::class];
    }
}
