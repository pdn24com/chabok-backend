<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PricingChargeTypeRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_charge_types';

    protected $guarded = ['*'];
}
