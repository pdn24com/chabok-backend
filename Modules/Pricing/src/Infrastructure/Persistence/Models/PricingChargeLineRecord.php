<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingChargeLineRecord extends Model
{
    protected $table = 'pricing_charge_lines';
    protected $primaryKey = 'charge_line_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
