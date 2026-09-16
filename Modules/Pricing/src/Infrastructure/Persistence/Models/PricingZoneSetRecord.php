<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingZoneSetRecord extends Model
{
    protected $table = 'pricing_zone_sets';
    protected $primaryKey = 'pricing_zone_set_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
