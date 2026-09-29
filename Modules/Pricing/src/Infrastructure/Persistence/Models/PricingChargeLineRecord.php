<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PricingChargeLineRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_charge_lines';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['explanation' => 'array'];
    }
}
