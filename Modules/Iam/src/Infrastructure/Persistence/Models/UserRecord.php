<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** Persistence mapping only; lifecycle rules belong to User domain policies. */
final class UserRecord extends Model
{
    use HasNumericIdentity;

    protected $table = 'users';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['must_change_password' => 'boolean'];
    }
}
