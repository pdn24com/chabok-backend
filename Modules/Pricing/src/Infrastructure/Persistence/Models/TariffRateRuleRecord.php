<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class TariffRateRuleRecord extends Model
{
    protected $table = 'tariff_rate_rules';
    protected $primaryKey = 'rate_rule_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
