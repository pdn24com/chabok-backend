<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Domain\Enums\InvitationStatus;

final class InvitationRecord extends Model
{
    use HasNumericIdentity;

    protected $table = 'user_invitations';

    protected $hidden = ['id', 'token_hash', 'normalized_recipient'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => InvitationStatus::class, 'expires_at' => 'immutable_datetime'];
    }
}
