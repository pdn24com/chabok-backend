<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ConsignmentPricingChargeLineRecord extends Model
{
    protected $table = 'consignment_pricing_charge_lines';
    protected $primaryKey = 'pricing_charge_line_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
