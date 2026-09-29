<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RouteDefinitionVersionLegRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_definition_version_legs';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['leg_order' => 'integer'];
    }
}
