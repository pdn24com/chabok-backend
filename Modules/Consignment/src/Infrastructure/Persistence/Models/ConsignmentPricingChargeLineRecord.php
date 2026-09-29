<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ConsignmentPricingChargeLineRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'consignment_pricing_charge_lines';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['explanation' => 'array'];
    }
}
