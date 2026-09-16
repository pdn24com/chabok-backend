<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CommitmentScheduleVersionRecord extends Model
{
    protected $table = 'commitment_schedule_versions';
    protected $primaryKey = 'commitment_schedule_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
