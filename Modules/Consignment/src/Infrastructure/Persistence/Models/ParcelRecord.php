<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class ParcelRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'parcels';

    protected $guarded = ['*'];

    public function activeRouteLeg(): BelongsTo
    {
        return $this->belongsTo(RoutePlanLegRecord::class, 'active_route_plan_leg_id', 'id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'current_node_id', 'id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(ConsignmentRecord::class, 'consignment_id', 'id');
    }

    public function manifestRows(): HasMany
    {
        return $this->hasMany(ManifestParcelRecord::class, 'parcel_id', 'id');
    }
}
