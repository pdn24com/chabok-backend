<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogIdentityRecordInterface;

final class CommitmentScheduleRecord extends Model implements CatalogIdentityRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'commitment_schedules';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(CommitmentScheduleVersionRecord::class, 'commitment_schedule_id', 'id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(CommitmentScheduleVersionRecord::class, 'commitment_schedule_id', 'id')->ofMany('version_number', 'max');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(CommitmentScheduleVersionRecord::class, 'commitment_schedule_id', 'id')
            ->ofMany(['version_number' => 'max', 'id' => 'max'], fn (Builder $version) => $version->where('status', VersionLifecycleStatus::Published->value));
    }

    public function legacyBindings(): HasManyThrough
    {
        return $this->hasManyThrough(OfferingCommitmentBindingRecord::class, CommitmentScheduleVersionRecord::class,
            'commitment_schedule_id', 'commitment_schedule_version_id', 'id', 'id');
    }
}
