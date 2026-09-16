<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class NodeRecord extends Model
{
    protected $table = 'nodes';
    protected $primaryKey = 'node_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
