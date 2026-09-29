<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RouteDefinitionVersionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_definition_versions';

    protected $guarded = ['*'];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(RouteDefinitionRecord::class, 'route_definition_id', 'id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(RouteDefinitionVersionLegRecord::class, 'route_definition_version_id', 'id')->orderBy('leg_order');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'version_number' => 'integer', 'priority' => 'integer'];
    }
}
