<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CoveragePolicyVersionRecord extends Model
{
    protected $table = 'coverage_policy_versions';
    protected $primaryKey = 'coverage_policy_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
