<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class OfferingCommitmentBindingRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_offering_commitment_bindings';

    protected $guarded = ['*'];

    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(CommitmentScheduleVersionRecord::class, 'commitment_schedule_version_id', 'id');
    }
}
