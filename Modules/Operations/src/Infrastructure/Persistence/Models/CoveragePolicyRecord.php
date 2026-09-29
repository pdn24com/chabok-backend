<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CoveragePolicyRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'coverage_policies';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(CoveragePolicyVersionRecord::class, 'coverage_policy_id', 'id');
    }
}
