<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final class TariffRateRuleRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'tariff_rate_rules';

    protected $guarded = ['*'];

    public function offeringVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceOfferingVersionRecord::class, 'service_offering_version_id', 'id');
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(PricingChargeTypeRecord::class, 'charge_type_id', 'id');
    }

    public function originZone(): BelongsTo
    {
        return $this->belongsTo(PricingZoneRecord::class, 'origin_zone_id', 'id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(PricingZoneRecord::class, 'destination_zone_id', 'id');
    }

    protected function casts(): array
    {
        return ['conditions' => 'array', 'basis_charge_codes' => 'array'];
    }
}
