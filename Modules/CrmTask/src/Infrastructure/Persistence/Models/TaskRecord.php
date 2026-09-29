<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CrmTask\Domain\Enums\TaskPriority;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class TaskRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_tasks';

    protected $guarded = ['*'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'assignee_id');
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'remind_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
