<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingIdentityRecordInterface;

final class PricingZoneSetRecord extends Model implements PricingIdentityRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_zone_sets';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(PricingZoneSetVersionRecord::class, 'pricing_zone_set_id', 'id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PricingZoneSetVersionRecord::class, 'pricing_zone_set_id', 'id')->ofMany('version_number', 'max');
    }
}
