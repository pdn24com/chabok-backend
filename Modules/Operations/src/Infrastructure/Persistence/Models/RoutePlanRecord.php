<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RoutePlanRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_plans';

    protected $guarded = ['*'];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(ConsignmentRecord::class, 'consignment_id', 'id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(RouteDefinitionRecord::class, 'route_definition_id', 'id');
    }

    public function definitionVersion(): BelongsTo
    {
        return $this->belongsTo(RouteDefinitionVersionRecord::class, 'route_definition_version_id', 'id');
    }

    public function evidence(): HasOne
    {
        return $this->hasOne(RoutePlanResolutionEvidenceRecord::class, 'route_plan_id', 'id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(RoutePlanLegRecord::class, 'route_plan_id', 'id')->orderBy('leg_order');
    }

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
