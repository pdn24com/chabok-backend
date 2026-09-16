<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OtpChallengeRecord extends Model
{
    protected $table = 'otp_challenges';
    protected $primaryKey = 'challenge_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
