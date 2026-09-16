<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CommitmentScheduleWindowRecord extends Model
{
    protected $table = 'commitment_schedule_windows';
    protected $primaryKey = 'commitment_schedule_window_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
