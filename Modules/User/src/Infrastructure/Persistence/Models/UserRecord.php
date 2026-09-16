<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
/** Persistence mapping only; lifecycle rules belong to User domain policies. */

final class UserRecord extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
