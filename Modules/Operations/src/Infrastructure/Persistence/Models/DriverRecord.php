<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Operations\Domain\Enums\DriverOperationalType;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;

final class DriverRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'drivers';

    protected $guarded = ['*'];

    public function capabilities(): HasMany
    {
        return $this->hasMany(DriverCapabilityRecord::class, 'driver_id', 'id');
    }

    protected function casts(): array
    {
        return ['status' => FleetStatus::class, 'availability_status' => FleetAvailability::class, 'operational_type' => DriverOperationalType::class, 'version' => 'integer'];
    }
}
