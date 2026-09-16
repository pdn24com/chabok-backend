<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CoveragePolicyRecord extends Model
{
    protected $table = 'coverage_policies';
    protected $primaryKey = 'coverage_policy_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
