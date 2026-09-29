<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class SessionRecord extends Model
{
    use HasNumericIdentity;

    protected $table = 'user_sessions';

    protected $hidden = ['id', 'refresh_token_hash', 'token_family_id', 'ip_address_hash', 'user_agent_hash'];

    protected $guarded = ['*'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'user_id', 'id');
    }

    protected function casts(): array
    {
        return ['rotation_counter' => 'integer'];
    }
}
