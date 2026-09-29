<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RouteDefinitionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_definitions';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(RouteDefinitionVersionRecord::class, 'route_definition_id', 'id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(RouteDefinitionLegRecord::class, 'route_definition_id', 'id')->orderBy('leg_order');
    }

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
