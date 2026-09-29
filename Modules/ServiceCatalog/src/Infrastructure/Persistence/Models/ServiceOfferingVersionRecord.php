<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;

final class ServiceOfferingVersionRecord extends Model implements CatalogVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_offering_versions';

    protected $guarded = ['*'];

    public function serviceTypeVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceTypeVersionRecord::class, 'service_type_version_id', 'id');
    }

    public function shippingMethodVersion(): BelongsTo
    {
        return $this->belongsTo(ShippingMethodVersionRecord::class, 'shipping_method_version_id', 'id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(ServiceOfferingRecord::class, 'service_offering_id', 'id');
    }

    public function optionRules(): HasMany
    {
        return $this->hasMany(OfferingOptionRuleRecord::class, 'service_offering_version_id', 'id');
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(ServiceEligibilityRuleRecord::class, 'service_offering_version_id', 'id');
    }

    public function coverageReferences(): HasMany
    {
        return $this->hasMany(ServiceCoverageReferenceRecord::class, 'service_offering_version_id', 'id');
    }

    public function availabilityBindings(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityBindingRecord::class, 'service_offering_version_id', 'id');
    }

    public function commitmentBinding(): HasOne
    {
        return $this->hasOne(OfferingCommitmentBindingRecord::class, 'service_offering_version_id', 'id');
    }

    protected function casts(): array
    {
        return ['labels' => 'array', 'sla_policy' => 'array', 'availability_summary' => 'array'];
    }
}
