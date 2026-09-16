<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingZoneSetVersionRecord extends Model
{
    protected $table = 'pricing_zone_set_versions';
    protected $primaryKey = 'zone_set_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
