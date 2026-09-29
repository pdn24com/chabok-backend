<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class DeliveryTaskHistoryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'delivery_task_history';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'event_sequence' => 'integer', 'attempt_number' => 'integer'];
    }
}
