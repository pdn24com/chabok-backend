<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AreaHierarchyRecord extends Model
{
    protected $table = 'area_hierarchies';
    protected $primaryKey = 'area_hierarchy_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
