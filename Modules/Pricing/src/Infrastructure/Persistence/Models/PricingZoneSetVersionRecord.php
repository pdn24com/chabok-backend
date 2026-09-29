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

final class PricingZoneSetVersionRecord extends Model implements PricingVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_zone_set_versions';

    protected $guarded = ['*'];

    public function zoneSet(): BelongsTo
    {
        return $this->belongsTo(PricingZoneSetRecord::class, 'pricing_zone_set_id', 'id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(PricingZoneRecord::class, 'zone_set_version_id', 'id');
    }

    public function scopePublishedAt(Builder $query, DateTimeInterface $at): void
    {
        $query->where('status', VersionLifecycleStatus::Published->value)->where('valid_from', '<=', $at)
            ->where(fn ($interval) => $interval->whereNull('valid_to')->orWhere('valid_to', '>', $at));
    }
}
