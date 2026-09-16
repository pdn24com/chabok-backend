<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CommitmentScheduleRecord extends Model
{
    protected $table = 'commitment_schedules';
    protected $primaryKey = 'commitment_schedule_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
