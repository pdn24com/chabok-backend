<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/** One CRM work team of a tenant. */
final class TeamRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_teams';

    protected $guarded = ['*'];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'supervisor_user_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(TeamMemberRecord::class, 'team_id')->where('status', MembershipStatus::ACTIVE->value);
    }

    protected function casts(): array
    {
        return ['status' => TeamStatus::class, 'created_at' => 'immutable_datetime'];
    }
}
