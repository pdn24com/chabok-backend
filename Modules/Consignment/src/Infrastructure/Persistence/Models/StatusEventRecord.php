<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class StatusEventRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'consignment_status_events';

    protected $guarded = ['*'];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(ConsignmentRecord::class, 'consignment_id', 'id');
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(ParcelRecord::class, 'parcel_id', 'id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'node_id', 'id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'initiator_id', 'id');
    }

    protected function casts(): array
    {
        return ['parcel_status_counts' => 'array', 'event_sequence' => 'integer'];
    }
}
