<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** One colleague's part in a meeting: who was there and for how many minutes. */
final class ActivityParticipantRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_activity_participants';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'activity_id' => 'string',
            'user_id' => 'string',
            'customer_id' => 'string',
            'created_by' => 'string',
            'minutes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
