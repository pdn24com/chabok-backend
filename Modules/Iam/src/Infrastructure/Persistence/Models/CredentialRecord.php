<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CredentialRecord extends Model
{
    use HasNumericIdentity;

    protected $table = 'authentication_credentials';

    protected $hidden = ['id', 'password_hash'];

    protected $guarded = ['*'];
}
