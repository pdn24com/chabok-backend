<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingIdentityRecordInterface;

final class TariffFamilyRecord extends Model implements PricingIdentityRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'tariff_families';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(TariffVersionRecord::class, 'tariff_family_id', 'id');
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(PricingChargeTypeRecord::class, 'service_charge_type_id', 'id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(TariffVersionRecord::class, 'tariff_family_id', 'id')->ofMany('version_number', 'max');
    }
}
