<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

final class PricingZoneMemberRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_zone_members';

    protected $guarded = ['*'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(PricingZoneRecord::class, 'pricing_zone_id', 'id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(CityRecord::class, 'city_id', 'id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(ProvinceRecord::class, 'province_id', 'id');
    }

    protected function casts(): array
    {
        return ['geometry' => 'array'];
    }
}
