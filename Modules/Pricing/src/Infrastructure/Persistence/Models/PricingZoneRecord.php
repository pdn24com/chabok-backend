<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PricingZoneRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_zones';

    protected $guarded = ['*'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(PricingZoneSetVersionRecord::class, 'zone_set_version_id', 'id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PricingZoneMemberRecord::class, 'pricing_zone_id', 'id');
    }
}
