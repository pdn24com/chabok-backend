<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final class ConsignmentRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'consignments';

    protected $guarded = ['*'];

    public function pickupNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'pickup_node_id', 'id');
    }

    public function deliveryNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'delivery_node_id', 'id');
    }

    public function pickupDriver(): BelongsTo
    {
        return $this->belongsTo(DriverRecord::class, 'pickup_man_id', 'id');
    }

    public function deliveryDriver(): BelongsTo
    {
        return $this->belongsTo(DriverRecord::class, 'delivery_man_id', 'id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(ParcelRecord::class, 'consignment_id', 'id');
    }

    public function pickupTasks(): HasMany
    {
        return $this->hasMany(PickupTaskRecord::class, 'consignment_id', 'id');
    }

    public function deliveryTasks(): HasMany
    {
        return $this->hasMany(DeliveryTaskRecord::class, 'consignment_id', 'id');
    }

    public function latestPricing(): HasOne
    {
        return $this->hasOne(ConsignmentPricingVersionRecord::class, 'consignment_id', 'id')->ofMany('version_number', 'max');
    }

    /** @param list<string> $nodeIds */
    public function scopeVisibleAtNodes(Builder $query, array $nodeIds): Builder
    {
        return $query->where(fn (Builder $visible) => $visible
            ->whereIn('consignments.pickup_node_id', $nodeIds)
            ->orWhereIn('consignments.delivery_node_id', $nodeIds)
            ->orWhereHas('parcels', fn (Builder $parcels) => $parcels->whereColumn('parcels.hq_id', 'consignments.hq_id')->whereIn('current_node_id', $nodeIds))
            ->orWhereHas('parcels', fn (Builder $parcels) => $parcels->whereColumn('parcels.hq_id', 'consignments.hq_id')
                ->whereHas('activeRouteLeg', fn (Builder $leg) => $leg->whereIn('destination_node_id', $nodeIds)->where('status', 'IN_TRANSIT')))
            ->orWhereHas('pickupTasks', fn (Builder $tasks) => $tasks->whereColumn('pickup_tasks.hq_id', 'consignments.hq_id')->whereIn('node_id', $nodeIds)->whereIn('status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))
            ->orWhereHas('deliveryTasks', fn (Builder $tasks) => $tasks->whereColumn('delivery_tasks.hq_id', 'consignments.hq_id')->whereIn('node_id', $nodeIds)->whereIn('status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS'])));
    }

    public function pricingVersions(): HasMany
    {
        return $this->hasMany(ConsignmentPricingVersionRecord::class, 'consignment_id', 'id')->orderByDesc('version_number');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEventRecord::class, 'consignment_id', 'id')
            ->orderBy('created_at')->orderByRaw('event_sequence IS NULL')->orderBy('event_sequence')->orderBy('status_event_id');
    }

    public function custodyEvents(): HasMany
    {
        return $this->hasMany(CustodyEventRecord::class, 'consignment_id', 'id')
            ->orderBy('created_at')->orderByRaw('event_sequence IS NULL')->orderBy('event_sequence')->orderBy('custody_event_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEventRecord::class, 'target_id', 'id')->where('target_type', 'CONSIGNMENT')->orderBy('created_at');
    }

    public function latestRoutePlan(): HasOne
    {
        return $this->hasOne(RoutePlanRecord::class, 'consignment_id', 'id')->latestOfMany('created_at');
    }

    public function offeringVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceOfferingVersionRecord::class, 'service_offering_version_id', 'id');
    }

    public function senderCity(): BelongsTo
    {
        return $this->belongsTo(CityRecord::class, 'sender_city_id', 'id');
    }

    public function receiverCity(): BelongsTo
    {
        return $this->belongsTo(CityRecord::class, 'receiver_city_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'parcel_status_counts' => 'array', 'catalog_snapshot' => 'array', 'commitment_snapshot' => 'array',
            'selected_service_option_versions' => 'array', 'delivery_commitment_resolution' => 'array',
            'version' => 'integer', 'insurance_enabled' => 'boolean', 'cod_enabled' => 'boolean',
        ];
    }
}
