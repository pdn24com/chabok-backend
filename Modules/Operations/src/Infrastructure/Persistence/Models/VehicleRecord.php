<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Domain\Enums\VehicleType;

final class VehicleRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'vehicles';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => FleetStatus::class, 'availability_status' => FleetAvailability::class, 'vehicle_type' => VehicleType::class, 'version' => 'integer', 'capacity_weight_grams' => 'integer', 'capacity_volume_cm3' => 'integer'];
    }
}
