<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class RouteDefinitionVersionRecord extends Model
{
    protected $table = 'route_definition_versions';
    protected $primaryKey = 'route_definition_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
