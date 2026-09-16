<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ServiceEligibilityRuleRecord extends Model
{
    protected $table = 'service_eligibility_rules';
    protected $primaryKey = 'eligibility_rule_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
