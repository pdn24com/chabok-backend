<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class RouteDefinitionLegRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'route_definition_legs';

    protected $guarded = ['*'];

    public function originNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'origin_node_id', 'id');
    }

    public function destinationNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'destination_node_id', 'id');
    }

    protected function casts(): array
    {
        return ['leg_order' => 'integer'];
    }
}
