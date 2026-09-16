<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingZoneMemberRecord extends Model
{
    protected $table = 'pricing_zone_members';
    protected $primaryKey = 'zone_member_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
