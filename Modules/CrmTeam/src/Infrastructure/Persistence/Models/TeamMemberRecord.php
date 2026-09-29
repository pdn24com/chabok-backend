<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/** One person's membership of one team, with the window it ran for. The CRM role lives in IAM, not here. */
final class TeamMemberRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_team_members';

    protected $guarded = ['*'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamRecord::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
