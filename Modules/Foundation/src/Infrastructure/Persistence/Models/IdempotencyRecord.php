<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class IdempotencyRecord extends Model
{
    protected $table = 'idempotency_records';
    protected $primaryKey = 'record_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
