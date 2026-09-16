<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CredentialRecord extends Model
{
    protected $table = 'authentication_credentials';
    protected $primaryKey = 'credential_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
