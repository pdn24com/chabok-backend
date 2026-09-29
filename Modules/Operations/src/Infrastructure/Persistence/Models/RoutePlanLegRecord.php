<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class RoutePlanLegRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_plan_legs';

    protected $guarded = ['*'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RoutePlanRecord::class, 'route_plan_id', 'id');
    }

    public function activeParcels(): HasMany
    {
        return $this->hasMany(ParcelRecord::class, 'active_route_plan_leg_id', 'id');
    }

    public function originNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'origin_node_id', 'id');
    }

    public function destinationNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'destination_node_id', 'id');
    }

    public function sourceVersionLeg(): BelongsTo
    {
        return $this->belongsTo(RouteDefinitionVersionLegRecord::class, 'source_route_definition_version_leg_id', 'id');
    }

    protected function casts(): array
    {
        return ['leg_order' => 'integer'];
    }
}
