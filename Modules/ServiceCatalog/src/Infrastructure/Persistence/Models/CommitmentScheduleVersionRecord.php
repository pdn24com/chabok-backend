<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;

final class CommitmentScheduleVersionRecord extends Model implements CatalogVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'commitment_schedule_versions';

    protected $guarded = ['*'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CommitmentScheduleRecord::class, 'commitment_schedule_id', 'id');
    }

    public function windows(): HasMany
    {
        return $this->hasMany(CommitmentScheduleWindowRecord::class, 'commitment_schedule_version_id', 'id')->orderBy('window_type')->orderBy('start_time');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(CommitmentScheduleScopeRecord::class, 'commitment_schedule_version_id', 'id');
    }

    protected function casts(): array
    {
        return ['commitment_policy' => 'array', 'version_number' => 'integer', 'lock_version' => 'integer'];
    }
}
