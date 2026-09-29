<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CoveragePolicyVersionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'coverage_policy_versions';

    protected $guarded = ['*'];

    public function rules(): HasMany
    {
        return $this->hasMany(CoverageRuleRecord::class, 'coverage_policy_version_id', 'id')->orderByDesc('priority');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CoveragePolicyRecord::class, 'coverage_policy_id', 'id');
    }

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'version' => 'integer'];
    }
}
