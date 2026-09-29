<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Domain\Enums\OtpPurpose;
use Modules\Iam\Domain\Enums\OtpStatus;

final class OtpChallengeRecord extends Model
{
    use HasNumericIdentity;

    public const UPDATED_AT = null;

    protected $table = 'otp_challenges';

    protected $hidden = ['id', 'code_hash', 'verification_token_hash', 'request_ip_hash'];

    protected $guarded = ['*'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'user_id', 'id');
    }

    protected function casts(): array
    {
        return ['status' => OtpStatus::class, 'expires_at' => 'immutable_datetime', 'purpose' => OtpPurpose::class, 'remaining_attempts' => 'integer'];
    }
}
