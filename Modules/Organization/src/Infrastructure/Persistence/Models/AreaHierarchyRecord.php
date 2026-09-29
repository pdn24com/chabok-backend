<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class AreaHierarchyRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'area_hierarchies';

    protected $guarded = ['*'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AreaRecord::class, 'parent_area_id', 'id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(AreaRecord::class, 'child_area_id', 'id');
    }

    protected function casts(): array
    {
        return ['parent_area_id' => 'integer', 'child_area_id' => 'integer'];
    }
}
