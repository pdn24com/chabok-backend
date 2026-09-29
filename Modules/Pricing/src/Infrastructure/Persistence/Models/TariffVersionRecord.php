<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingVersionRecordInterface;

final class TariffVersionRecord extends Model implements PricingVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'tariff_versions';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(TariffFamilyRecord::class, 'tariff_family_id', 'id');
    }

    public function zoneVersion(): BelongsTo
    {
        return $this->belongsTo(PricingZoneSetVersionRecord::class, 'zone_set_version_id', 'id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TariffRateRuleRecord::class, 'tariff_version_id', 'id')->orderBy('priority');
    }

    public function serviceAttachments(): HasMany
    {
        return $this->hasMany(TariffServiceAttachmentRecord::class, 'tariff_version_id', 'id');
    }

    public function scopePublishedAt(Builder $query, DateTimeInterface $at): void
    {
        $query->where('status', VersionLifecycleStatus::Published->value)->where('valid_from', '<=', $at)
            ->where(fn ($interval) => $interval->whereNull('valid_to')->orWhere('valid_to', '>', $at));
    }

    protected function casts(): array
    {
        return ['freight_matrices' => 'array', 'version_number' => 'integer', 'is_default' => 'boolean', 'valid_from' => 'immutable_datetime', 'valid_to' => 'immutable_datetime'];
    }
}
