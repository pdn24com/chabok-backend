<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CrmTeam\Domain\Enums\MembershipEventType;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** Append-only trail of membership changes; a bulk run ties its rows together through operation_id. */
final class MembershipEventRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_membership_events';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'event_type' => MembershipEventType::class,
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
