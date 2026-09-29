<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class RoutePlanResolutionEvidenceRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_plan_resolution_evidence';

    protected $guarded = ['*'];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CoveragePolicyRecord::class, 'coverage_policy_id', 'id');
    }

    public function coverageVersion(): BelongsTo
    {
        return $this->belongsTo(CoveragePolicyVersionRecord::class, 'coverage_policy_version_id', 'id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'destination_gateway_node_id', 'id');
    }

    protected function casts(): array
    {
        return ['resolution_input' => 'array', 'matched_geography_evidence' => 'array', 'matched_postal_evidence' => 'array', 'matched_geometry_evidence' => 'array', 'ordered_route_legs' => 'array'];
    }
}
