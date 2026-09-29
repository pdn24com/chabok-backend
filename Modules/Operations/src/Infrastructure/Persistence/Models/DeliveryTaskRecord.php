<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Operations\Domain\Enums\DeliveryTaskStatus;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class DeliveryTaskRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'delivery_tasks';

    protected $guarded = ['*'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'node_id', 'id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(DriverRecord::class, 'assigned_driver_id', 'id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(ParcelRecord::class, 'consignment_id', 'consignment_id')->orderBy('parcel_number');
    }

    public function history(): HasMany
    {
        return $this->hasMany(DeliveryTaskHistoryRecord::class, 'delivery_task_id', 'id')->orderBy('event_sequence');
    }

    public function resolution(): HasOne
    {
        return $this->hasOne(LastMileResolutionRecord::class, 'consignment_id', 'consignment_id');
    }

    public function routeLegs(): HasManyThrough
    {
        return $this->hasManyThrough(RoutePlanLegRecord::class, RoutePlanRecord::class,
            'consignment_id', 'route_plan_id', 'consignment_id', 'id')->orderBy('leg_order');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(ConsignmentRecord::class, 'consignment_id', 'id');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'attempt_number' => 'integer', 'status' => DeliveryTaskStatus::class];
    }
}
