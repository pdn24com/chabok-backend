<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Dto\ConsignmentDriverOptionsDto;
use Modules\Consignment\Application\Dto\ConsignmentFiltersDto;
use Modules\Consignment\Application\Dto\ConsignmentStatusGroupTotalsDto;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final class EloquentConsignmentRepository implements ConsignmentRepositoryInterface
{
    /** Relations a list row renders; each is tenant-scoped so a foreign row can never leak in. */
    private const LIST_RELATIONS = ['pickupNode', 'deliveryNode', 'pickupDriver', 'deliveryDriver', 'latestPricing'];

    /** Relations a detail response renders on top of the list ones. */
    private const DETAIL_RELATIONS = ['pickupNode', 'deliveryNode', 'pickupDriver', 'deliveryDriver', 'latestPricing',
        'pricingVersions', 'statusEvents', 'custodyEvents', 'latestRoutePlan'];

    /** Aggregate columns a projection overwrites on an already-locked row. */
    private const AGGREGATE_COLUMNS = ['current_status', 'aggregate_mode', 'parcel_status_counts', 'updated_at'];

    public function paginateVisible(string $hqId, array $nodeIds, ConsignmentFiltersDto $filters): LengthAwarePaginator
    {
        $query = $this->visible($hqId, $nodeIds)
            ->whereHas('pickupNode', fn ($node) => $node->where('hq_id', $hqId))
            ->withCount(['parcels as parcel_count' => fn ($parcels) => $parcels->where('hq_id', $hqId)]);
        foreach (self::LIST_RELATIONS as $relation) {
            $query->with([$relation => fn ($related) => $related->where('hq_id', $hqId)]);
        }

        return $this->applyFilters($query, $filters)
            ->orderBy('consignments.'.$filters->sortField, $filters->sortDirection)
            ->orderBy('consignments.consignment_id', $filters->sortDirection)
            ->paginate($filters->pageSize, ['consignments.*'], 'page', $filters->page);
    }

    public function statusGroupTotals(string $hqId, array $nodeIds, ConsignmentFiltersDto $filters): ConsignmentStatusGroupTotalsDto
    {
        // SQL aggregation keeps counts independent of page size without hydrating every consignment.
        $row = $this->applyFilters($this->visible($hqId, $nodeIds), $filters, includeStatus: false)
            ->selectRaw($this->statusGroupSelection())->first();

        return new ConsignmentStatusGroupTotalsDto(
            total: (int) ($row->total ?? 0),
            newRouted: (int) ($row->new_routed ?? 0),
            unassigned: (int) ($row->unassigned ?? 0),
            assigned: (int) ($row->assigned ?? 0),
            inOperation: (int) ($row->in_operation ?? 0),
            exception: (int) ($row->exception ?? 0),
            completed: (int) ($row->completed ?? 0),
            cancelled: (int) ($row->cancelled ?? 0),
        );
    }

    public function driverOptions(string $hqId, array $nodeIds): ConsignmentDriverOptionsDto
    {
        $visible = $this->visible($hqId, $nodeIds);
        $drivers = DriverRecord::query()->where('hq_id', $hqId)->orderBy('display_name');

        return new ConsignmentDriverOptionsDto(
            pickupDrivers: (clone $drivers)->whereIn('id', (clone $visible)->select('pickup_man_id'))->get(),
            deliveryDrivers: (clone $drivers)->whereIn('id', (clone $visible)->select('delivery_man_id'))->get(),
        );
    }

    public function visibleIds(string $hqId, array $nodeIds): array
    {
        return $this->visible($hqId, $nodeIds)->orderBy('consignment_id')->pluck('consignment_id')->map(strval(...))->all();
    }

    public function findVisibleDetail(string $hqId, array $nodeIds, string $consignmentId, bool $includeAudit): ?ConsignmentRecord
    {
        $query = $this->visible($hqId, $nodeIds)
            ->where('consignment_id', $consignmentId)->whereHas('pickupNode', fn ($node) => $node->where('hq_id', $hqId))
            ->withCount(['parcels as parcel_count' => fn ($parcels) => $parcels->where('hq_id', $hqId)]);
        $relations = [];
        foreach (self::DETAIL_RELATIONS as $relation) {
            $relations[$relation] = fn ($related) => $related->where('hq_id', $hqId);
        }
        $relations += [
            'parcels' => fn ($parcels) => $parcels->where('hq_id', $hqId)->orderBy('parcel_number'),
            'parcels.node' => fn ($node) => $node->where('hq_id', $hqId),
            'pricingVersions.chargeLines' => fn ($lines) => $lines->where('hq_id', $hqId),
            'senderCity.province' => fn ($province) => $province,
            'receiverCity.province' => fn ($province) => $province,
            'latestRoutePlan.legs' => fn ($legs) => $legs->where('hq_id', $hqId)->whereHas('originNode')->whereHas('destinationNode')->with(['originNode', 'destinationNode']),
            'offeringVersion' => fn ($version) => $version->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))
                ->whereHas('serviceTypeVersion')->whereHas('shippingMethodVersion')->with(['serviceTypeVersion', 'shippingMethodVersion']),
        ];
        if ($includeAudit) {
            $relations['auditEvents'] = fn ($audit) => $audit->where('hq_id', $hqId);
        }

        return $query->with($relations)->first();
    }

    public function lockVisible(string $hqId, array $nodeIds, string $consignmentId): ?ConsignmentRecord
    {
        return $this->visible($hqId, $nodeIds)
            ->where('consignment_id', $consignmentId)
            ->whereHas('pickupNode', fn ($node) => $node->where('hq_id', $hqId))
            ->lockForUpdate()->first();
    }

    public function lockByIds(string $hqId, array $consignmentIds): Collection
    {
        return ConsignmentRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->orderBy('consignment_id')->lockForUpdate()->get();
    }

    public function lockParentsOfParcels(string $hqId, array $parcelIds): void
    {
        ConsignmentRecord::query()->where('hq_id', $hqId)
            ->whereIn('id', ParcelRecord::query()->select('consignment_id')->where('hq_id', $hqId)->whereIn('parcel_id', $parcelIds))
            ->orderBy('consignment_id')->lockForUpdate()->get();
    }

    public function assignPickupDriver(?string $hqId, array $consignmentIds, ?string $driverId): void
    {
        ConsignmentRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)->update(['pickup_man_id' => $driverId]);
    }

    public function lockAtPickupNode(?string $hqId, string $consignmentId, string $nodeId): ?ConsignmentRecord
    {
        return ConsignmentRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId])->lockForUpdate()->first();
    }

    public function findConfirmedAtPickupNode(?string $hqId, string $consignmentId, string $nodeId): ?ConsignmentRecord
    {
        return ConsignmentRecord::query()
            ->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId, 'consignment_id' => $consignmentId, 'current_status' => ConsignmentStatus::Confirmed->value])->first();
    }

    public function upsertAggregates(array $rows): void
    {
        ConsignmentRecord::query()->upsert($rows, ['id'], self::AGGREGATE_COLUMNS);
    }

    /** @param list<string> $nodeIds */
    private function visible(string $hqId, array $nodeIds): Builder
    {
        return ConsignmentRecord::query()->where('consignments.hq_id', $hqId)->visibleAtNodes($nodeIds);
    }

    private function applyFilters(Builder $query, ConsignmentFiltersDto $filters, bool $includeStatus = true): Builder
    {
        if ($filters->search !== null) {
            $term = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(function (Builder $query) use ($term): void {
                $query
                    ->where('consignments.consignment_number', 'like', $term)
                    ->orWhere('consignments.receiver_contact_name', 'like', $term)
                    ->orWhere('consignments.receiver_mobile', 'like', $term)
                    ->orWhereHas('parcels', fn (Builder $parcels) => $parcels->whereColumn('parcels.hq_id', 'consignments.hq_id')->where('parcel_number', 'like', $term));
            });
        }
        $selections = [
            'current_status' => $includeStatus ? $filters->statuses : [],
            'pickup_node_id' => $filters->pickupNodeIds,
            'delivery_node_id' => $filters->deliveryNodeIds,
            'pickup_man_id' => $filters->pickupDriverIds,
            'delivery_man_id' => $filters->deliveryDriverIds,
            'service_type_id' => $filters->serviceTypeId === null ? [] : [$filters->serviceTypeId],
            'shipping_method_id' => $filters->shippingMethodId === null ? [] : [$filters->shippingMethodId],
        ];
        foreach ($selections as $column => $values) {
            if ($values !== []) {
                $query->whereIn('consignments.'.$column, $values);
            }
        }
        if ($includeStatus && $filters->statusGroup !== null) {
            $group = $filters->statusGroup->value;
            if ($group === 'UNASSIGNED') {
                $query->whereNull('consignments.pickup_man_id')->whereNull('consignments.delivery_man_id');
            } elseif ($group === 'ASSIGNED') {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('consignments.pickup_man_id')->orWhereNotNull('consignments.delivery_man_id');
                });
            } else {
                $query->whereExists(fn ($q) => $q
                    ->selectRaw('1')
                    ->from('operational_statuses as st')
                    ->whereColumn('st.code', 'consignments.current_status')
                    ->where(fn ($q) => $q->whereNull('st.hq_id')->orWhereColumn('st.hq_id', 'consignments.hq_id'))
                    ->where('st.status_group', $group));
            }
        }
        if ($filters->createdFrom !== null) {
            $query->where('consignments.created_at', '>=', $filters->createdFrom->format('Y-m-d H:i:s.u'));
        }
        if ($filters->slaRisks !== []) {
            $awaitingPickup = implode(',', array_map(
                static fn (ConsignmentStatus $status): string => "'{$status->value}'",
                ConsignmentStatus::awaitingPickup(),
            ));
            $pickup = "consignments.current_status IN ({$awaitingPickup}) AND consignments.pickup_commitment_at IS NOT NULL";
            $deadline = "CASE WHEN {$pickup} THEN consignments.pickup_commitment_at ELSE consignments.delivery_commitment_at END";
            $minutes = "CASE WHEN {$pickup} THEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(consignments.commitment_snapshot, '\$.pickup.risk_threshold_minutes')), JSON_UNQUOTE(JSON_EXTRACT(consignments.commitment_snapshot, '\$.pickup.selected.risk_threshold_minutes')), 120) ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(consignments.commitment_snapshot, '\$.delivery.risk_threshold_minutes')), JSON_UNQUOTE(JSON_EXTRACT(consignments.commitment_snapshot, '\$.delivery.selected.risk_threshold_minutes')), 120) END";
            $now = CarbonImmutable::now()->utc()->format('Y-m-d H:i:s');
            $query->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('operational_statuses as st')
                ->whereColumn('st.code', 'consignments.current_status')
                ->where(fn ($q) => $q->whereNull('st.hq_id')->orWhereColumn('st.hq_id', 'consignments.hq_id'))
                ->where('st.is_terminal', false));
            $query->where(function (Builder $risks) use ($filters, $deadline, $minutes, $now): void {
                foreach ($filters->slaRisks as $risk) {
                    $risks->orWhere(function (Builder $selection) use ($risk, $deadline, $minutes, $now): void {
                        match ($risk->value) {
                            'OVERDUE' => $selection->whereRaw("({$deadline}) < ?", [$now]),
                            'AT_RISK' => $selection->whereRaw("({$deadline}) >= ? AND ({$deadline}) <= TIMESTAMPADD(MINUTE, CAST(({$minutes}) AS UNSIGNED), ?)", [$now, $now]),
                            'ON_TIME' => $selection->whereRaw("({$deadline}) > TIMESTAMPADD(MINUTE, CAST(({$minutes}) AS UNSIGNED), ?)", [$now]),
                            'NO_COMMITMENT' => $selection->whereRaw("({$deadline}) IS NULL"),
                            default => null,
                        };
                    });
                }
            });
        }
        if ($filters->createdTo !== null) {
            $query->where('consignments.created_at', '<=', $filters->createdTo->format('Y-m-d H:i:s.u'));
        }

        return $query;
    }

    private function statusGroupSelection(): string
    {
        $group = fn (string $statusGroup): string => "SUM(CASE WHEN EXISTS (SELECT 1 FROM operational_statuses st WHERE st.code=consignments.current_status AND (st.hq_id IS NULL OR st.hq_id=consignments.hq_id) AND st.status_group='{$statusGroup}') THEN 1 ELSE 0 END)";

        return "COUNT(*) AS total,\r\n            {$group('NEW_ROUTED')} AS new_routed,\r\n            SUM(CASE WHEN consignments.pickup_man_id IS NULL AND consignments.delivery_man_id IS NULL THEN 1 ELSE 0 END) AS unassigned,\r\n            SUM(CASE WHEN consignments.pickup_man_id IS NOT NULL OR consignments.delivery_man_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned,\r\n            {$group('IN_OPERATION')} AS in_operation,\r\n            {$group('EXCEPTION')} AS exception,\r\n            {$group('COMPLETED')} AS completed,\r\n            {$group('CANCELLED')} AS cancelled";
    }
}
