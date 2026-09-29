<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class NodeRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'nodes';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'address_snapshot' => 'array'];
    }
}
