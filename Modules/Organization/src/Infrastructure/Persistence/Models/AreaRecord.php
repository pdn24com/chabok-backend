<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class AreaRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'areas';

    protected $guarded = ['*'];

    public function parentEdge(): HasOne
    {
        return $this->hasOne(AreaHierarchyRecord::class, 'child_area_id', 'id');
    }
}
