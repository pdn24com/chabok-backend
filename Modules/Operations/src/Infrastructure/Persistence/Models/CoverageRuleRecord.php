<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class CoverageRuleRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'coverage_rules';

    protected $guarded = ['*'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CoveragePolicyVersionRecord::class, 'coverage_policy_version_id', 'id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'target_node_id', 'id');
    }

    protected function casts(): array
    {
        return ['geometry_geojson' => 'array', 'priority' => 'integer', 'radius_meters' => 'integer'];
    }
}
