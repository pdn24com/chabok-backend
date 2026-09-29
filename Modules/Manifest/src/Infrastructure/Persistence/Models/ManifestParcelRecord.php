<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;

final class ManifestParcelRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'manifest_parcels';

    protected $guarded = ['*'];

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(ParcelRecord::class, 'parcel_id', 'id');
    }

    public function routeLeg(): BelongsTo
    {
        return $this->belongsTo(RoutePlanLegRecord::class, 'route_plan_leg_id', 'id');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(ManifestRecord::class, 'manifest_id', 'id');
    }
}
