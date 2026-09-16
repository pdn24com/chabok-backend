<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class SessionRecord extends Model
{
    protected $table = 'user_sessions';
    protected $primaryKey = 'session_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
