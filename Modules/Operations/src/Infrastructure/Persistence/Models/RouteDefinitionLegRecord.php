<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class RouteDefinitionLegRecord extends Model
{
    protected $table = 'route_definition_legs';
    protected $primaryKey = 'route_definition_leg_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
