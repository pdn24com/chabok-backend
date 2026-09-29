<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** One move of a task between owners. The row is written once and never edited. */
final class TaskAssignmentEventRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_task_assignment_events';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'event_type' => TaskAssignmentEventType::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
