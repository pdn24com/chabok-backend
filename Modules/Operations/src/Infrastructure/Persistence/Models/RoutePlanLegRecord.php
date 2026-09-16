<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class RoutePlanLegRecord extends Model
{
    protected $table = 'route_plan_legs';
    protected $primaryKey = 'route_plan_leg_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
