<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingChargeTypeRecord extends Model
{
    protected $table = 'pricing_charge_types';
    protected $primaryKey = 'charge_type_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
