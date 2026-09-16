<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OfferingOptionRuleRecord extends Model
{
    protected $table = 'service_offering_option_rules';
    protected $primaryKey = 'offering_option_rule_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
