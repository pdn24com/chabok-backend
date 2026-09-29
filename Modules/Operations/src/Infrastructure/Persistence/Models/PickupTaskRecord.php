<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Operations\Domain\Enums\PickupTaskStatus;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class PickupTaskRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pickup_tasks';

    protected $guarded = ['*'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'node_id', 'id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(DriverRecord::class, 'assigned_driver_id', 'id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(ConsignmentRecord::class, 'consignment_id', 'id');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'status' => PickupTaskStatus::class];
    }
}
