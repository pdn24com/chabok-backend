<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CoverageRuleRecord extends Model
{
    protected $table = 'coverage_rules';
    protected $primaryKey = 'coverage_rule_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
