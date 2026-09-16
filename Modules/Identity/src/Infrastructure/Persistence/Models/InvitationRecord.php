<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class InvitationRecord extends Model
{
    protected $table = 'user_invitations';
    protected $primaryKey = 'invitation_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
