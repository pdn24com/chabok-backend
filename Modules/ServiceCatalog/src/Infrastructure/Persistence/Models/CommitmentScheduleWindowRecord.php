<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CommitmentScheduleWindowRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'commitment_schedule_windows';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['applicable_weekdays' => 'array', 'day_offset' => 'integer', 'risk_threshold_minutes' => 'integer'];
    }
}
