<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class RoutePlanResolutionEvidenceRecord extends Model
{
    protected $table = 'route_plan_resolution_evidence';
    protected $primaryKey = 'resolution_evidence_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
