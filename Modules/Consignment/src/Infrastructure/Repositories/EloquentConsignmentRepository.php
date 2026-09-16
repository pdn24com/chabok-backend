<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Data\Page;
use Modules\Consignment\Application\Repositories\ConsignmentRepository;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingVersionRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingChargeLineRecord;

final class EloquentConsignmentRepository implements ConsignmentRepository
{
    public function list(string $hqId, string $nodeId, array $filters): Page
    {
        $query = ConsignmentRecord::query()->toBase()->from('consignments as c')->join('nodes as pickup_node', function ($join): void {
            $join->on('pickup_node.node_id', '=', 'c.pickup_node_id')->on('pickup_node.hq_id', '=', 'c.hq_id');
        })->leftJoin('nodes as delivery_node', function ($join): void {
            $join->on('delivery_node.node_id', '=', 'c.delivery_node_id')->on('delivery_node.hq_id', '=', 'c.hq_id');
        })->where('c.hq_id', $hqId)->where(fn(Builder $query) => $this->applyNodeVisibility($query, $nodeId))->select([
            'c.consignment_id',
            'c.consignment_number',
            'c.receiver_contact_name',
            'c.receiver_mobile',
            'c.receiver_address_text',
            'c.pickup_node_id',
            'pickup_node.node_title as pickup_node_title',
            'c.delivery_node_id',
            'delivery_node.node_title as delivery_node_title',
            'c.pickup_man_id',
            'c.delivery_man_id',
            'c.current_status',
            'c.aggregate_mode',
            'c.parcel_status_counts',
            'c.version',
            'c.created_at',
            'c.updated_at',
        ])->selectSub(ParcelRecord::query()->toBase()->from('parcels as p')->selectRaw('COUNT(*)')->whereColumn('p.consignment_id', 'c.consignment_id')->whereColumn('p.hq_id', 'c.hq_id'), 'parcel_count')->selectSub(ConsignmentPricingVersionRecord::query()->toBase()->from('consignment_pricing_versions as cpv')->select('cpv.total_amount')->whereColumn('cpv.consignment_id', 'c.consignment_id')->whereColumn('cpv.hq_id', 'c.hq_id')->orderByDesc('cpv.version_number')->limit(1), 'payable_total_amount')->selectSub(ConsignmentPricingVersionRecord::query()->toBase()->from('consignment_pricing_versions as cpv')->select('cpv.currency')->whereColumn('cpv.consignment_id', 'c.consignment_id')->whereColumn('cpv.hq_id', 'c.hq_id')->orderByDesc('cpv.version_number')->limit(1), 'payable_currency');
        foreach (['pickup', 'delivery'] as $role) {
            $query->selectSub(DB::table('drivers as driver')->select('driver.display_name')->whereColumn('driver.driver_id', 'c.' . $role . '_man_id')->whereColumn('driver.hq_id', 'c.hq_id')->limit(1), $role . '_man_title');
        }
        $this->applyFilters($query, $filters);
        [$sortField, $sortDirection] = $this->sort((string) ($filters['sort'] ?? '-created_at'));
        $query->orderBy("c.{$sortField}", $sortDirection)->orderBy('c.consignment_id', $sortDirection);
        $page = $query->paginate(perPage: (int) ($filters['page_size'] ?? 25), page: (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function filterOptions(string $hqId, string $nodeId): array
    {
        $options = [];
        foreach (['pickup', 'delivery'] as $role) {
            $options[$role . '_agents'] = ConsignmentRecord::query()->toBase()->from('consignments as c')->join('drivers as d', fn($join) => $join->on('d.driver_id', '=', 'c.' . $role . '_man_id')->on('d.hq_id', '=', 'c.hq_id'))->where('c.hq_id', $hqId)->where(fn(Builder $query) => $this->applyNodeVisibility($query, $nodeId))->distinct()->orderBy('d.display_name')->get(['d.driver_id as value', 'd.display_name as label'])->all();
        }
        return $options;
    }

    public function statusGroupCounts(string $hqId, string $nodeId, array $filters): array
    {
        unset($filters['status'], $filters['status_group'], $filters['page'], $filters['page_size'], $filters['sort']);
        $query = ConsignmentRecord::query()->toBase()->from('consignments as c')->where('c.hq_id', $hqId)->where(fn(Builder $query) => $this->applyNodeVisibility($query, $nodeId));
        $this->applyFilters($query, $filters);
        $row = (array) $query->selectRaw("COUNT(*) AS total,\r\n            SUM(CASE WHEN EXISTS (SELECT 1 FROM operational_statuses st WHERE st.code=c.current_status AND (st.hq_id IS NULL OR st.hq_id=c.hq_id) AND st.status_group='NEW_ROUTED') THEN 1 ELSE 0 END) AS new_routed,\r\n            SUM(CASE WHEN c.pickup_man_id IS NULL AND c.delivery_man_id IS NULL THEN 1 ELSE 0 END) AS unassigned,\r\n            SUM(CASE WHEN c.pickup_man_id IS NOT NULL OR c.delivery_man_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned,\r\n            SUM(CASE WHEN EXISTS (SELECT 1 FROM operational_statuses st WHERE st.code=c.current_status AND (st.hq_id IS NULL OR st.hq_id=c.hq_id) AND st.status_group='IN_OPERATION') THEN 1 ELSE 0 END) AS in_operation,\r\n            SUM(CASE WHEN EXISTS (SELECT 1 FROM operational_statuses st WHERE st.code=c.current_status AND (st.hq_id IS NULL OR st.hq_id=c.hq_id) AND st.status_group='EXCEPTION') THEN 1 ELSE 0 END) AS exception,\r\n            SUM(CASE WHEN EXISTS (SELECT 1 FROM operational_statuses st WHERE st.code=c.current_status AND (st.hq_id IS NULL OR st.hq_id=c.hq_id) AND st.status_group='COMPLETED') THEN 1 ELSE 0 END) AS completed,\r\n            SUM(CASE WHEN EXISTS (SELECT 1 FROM operational_statuses st WHERE st.code=c.current_status AND (st.hq_id IS NULL OR st.hq_id=c.hq_id) AND st.status_group='CANCELLED') THEN 1 ELSE 0 END) AS cancelled")->first();
        return [
            'total' => (int) ($row['total'] ?? 0),
            'new_routed' => (int) ($row['new_routed'] ?? 0),
            'unassigned' => (int) ($row['unassigned'] ?? 0),
            'assigned' => (int) ($row['assigned'] ?? 0),
            'in_operation' => (int) ($row['in_operation'] ?? 0),
            'exception' => (int) ($row['exception'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }

    private function visibleQuery(string $hqId, array $nodeIds): Builder
    {
        return ConsignmentRecord::query()->toBase()->from('consignments as c')->join('nodes as pickup_node', function ($join): void {
            $join->on('pickup_node.node_id', '=', 'c.pickup_node_id')->on('pickup_node.hq_id', '=', 'c.hq_id');
        })->leftJoin('nodes as delivery_node', function ($join): void {
            $join->on('delivery_node.node_id', '=', 'c.delivery_node_id')->on('delivery_node.hq_id', '=', 'c.hq_id');
        })->where('c.hq_id', $hqId)->where(function (Builder $query) use ($nodeIds): void {
            foreach ($nodeIds as $index => $nodeId) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}(fn(Builder $nodeQuery) => $this->applyNodeVisibility($nodeQuery, (string) $nodeId));
            }
        })->select(['c.*', 'pickup_node.node_title as pickup_node_title', 'delivery_node.node_title as delivery_node_title'])->selectSub(ParcelRecord::query()->toBase()->from('parcels as p')->selectRaw('COUNT(*)')->whereColumn('p.consignment_id', 'c.consignment_id')->whereColumn('p.hq_id', 'c.hq_id'), 'parcel_count')->selectSub(ConsignmentPricingVersionRecord::query()->toBase()->from('consignment_pricing_versions as cpv')->select('cpv.total_amount')->whereColumn('cpv.consignment_id', 'c.consignment_id')->whereColumn('cpv.hq_id', 'c.hq_id')->orderByDesc('cpv.version_number')->limit(1), 'payable_total_amount')->selectSub(ConsignmentPricingVersionRecord::query()->toBase()->from('consignment_pricing_versions as cpv')->select('cpv.currency')->whereColumn('cpv.consignment_id', 'c.consignment_id')->whereColumn('cpv.hq_id', 'c.hq_id')->orderByDesc('cpv.version_number')->limit(1), 'payable_currency');
    }

    private function applyNodeVisibility(Builder $query, string $nodeId): void
    {
        $query->where('c.pickup_node_id', $nodeId)->orWhere('c.delivery_node_id', $nodeId)->orWhereExists(fn($q) => $q->selectRaw('1')->from('parcels as pv')->whereColumn('pv.consignment_id', 'c.consignment_id')->whereColumn('pv.hq_id', 'c.hq_id')->where('pv.current_node_id', $nodeId))->orWhereExists(fn($q) => $q->selectRaw('1')->from('parcels as pt')->join('route_plan_legs as rpl', 'rpl.route_plan_leg_id', '=', 'pt.active_route_plan_leg_id')->whereColumn('pt.consignment_id', 'c.consignment_id')->whereColumn('pt.hq_id', 'c.hq_id')->where('rpl.destination_node_id', $nodeId)->where('rpl.status', 'IN_TRANSIT'))->orWhereExists(fn($q) => $q->selectRaw('1')->from('pickup_tasks as ptask')->whereColumn('ptask.consignment_id', 'c.consignment_id')->whereColumn('ptask.hq_id', 'c.hq_id')->where('ptask.node_id', $nodeId)->whereIn('ptask.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))->orWhereExists(fn($q) => $q->selectRaw('1')->from('delivery_tasks as dtask')->whereColumn('dtask.consignment_id', 'c.consignment_id')->whereColumn('dtask.hq_id', 'c.hq_id')->where('dtask.node_id', $nodeId)->whereIn('dtask.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? null) !== null) {
            $term = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('c.consignment_number', 'like', $term)->orWhere('c.receiver_contact_name', 'like', $term)->orWhere('c.receiver_mobile', 'like', $term)->orWhereExists(function (Builder $subquery) use ($term): void {
                    $subquery->selectRaw('1')->from('parcels as sp')->whereColumn('sp.consignment_id', 'c.consignment_id')->whereColumn('sp.hq_id', 'c.hq_id')->where('sp.parcel_number', 'like', $term);
                });
            });
        }
        foreach ([
            'status' => 'current_status',
            'pickup_node_id' => 'pickup_node_id',
            'delivery_node_id' => 'delivery_node_id',
            'pickup_man_id' => 'pickup_man_id',
            'delivery_man_id' => 'delivery_man_id',
            'service_type_id' => 'service_type_id',
            'shipping_method_id' => 'shipping_method_id',
        ] as $input => $column) {
            if (!empty($filters[$input])) {
                $query->whereIn("c.{$column}", (array) $filters[$input]);
            }
        }
        if (($filters['status_group'] ?? null) !== null) {
            $group = (string) $filters['status_group'];
            if ($group === 'UNASSIGNED') {
                $query->whereNull('c.pickup_man_id')->whereNull('c.delivery_man_id');
            } elseif ($group === 'ASSIGNED') {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('c.pickup_man_id')->orWhereNotNull('c.delivery_man_id');
                });
            } else {
                $query->whereExists(fn($q) => $q->selectRaw('1')->from('operational_statuses as st')->whereColumn('st.code', 'c.current_status')->where(fn($q) => $q->whereNull('st.hq_id')->orWhereColumn('st.hq_id', 'c.hq_id'))->where('st.status_group', $group));
            }
        }
        if (($filters['created_from'] ?? null) !== null) {
            $query->where('c.created_at', '>=', CarbonImmutable::parse($filters['created_from'])->utc()->format('Y-m-d H:i:s.u'));
        }
        if (!empty($filters['sla_risk'])) {
            $pickup = "c.current_status IN ('D00','CFM','PD','NPU') AND c.pickup_commitment_at IS NOT NULL";
            $deadline = "CASE WHEN {$pickup} THEN c.pickup_commitment_at ELSE c.delivery_commitment_at END";
            $minutes = "CASE WHEN {$pickup} THEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(c.commitment_snapshot, '\$.pickup.risk_threshold_minutes')), JSON_UNQUOTE(JSON_EXTRACT(c.commitment_snapshot, '\$.pickup.selected.risk_threshold_minutes')), 120) ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(c.commitment_snapshot, '\$.delivery.risk_threshold_minutes')), JSON_UNQUOTE(JSON_EXTRACT(c.commitment_snapshot, '\$.delivery.selected.risk_threshold_minutes')), 120) END";
            $now = CarbonImmutable::now()->utc()->format('Y-m-d H:i:s');
            $query->whereExists(fn($q) => $q->selectRaw('1')->from('operational_statuses as st')->whereColumn('st.code', 'c.current_status')->where(fn($q) => $q->whereNull('st.hq_id')->orWhereColumn('st.hq_id', 'c.hq_id'))->where('st.is_terminal', false));
            $query->where(function (Builder $risks) use ($filters, $deadline, $minutes, $now): void {
                foreach ((array) $filters['sla_risk'] as $risk) {
                    $risks->orWhere(function (Builder $selection) use ($risk, $deadline, $minutes, $now): void {
                        match ($risk) {
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
        if (($filters['created_to'] ?? null) !== null) {
            $query->where('c.created_at', '<=', CarbonImmutable::parse($filters['created_to'])->utc()->format('Y-m-d H:i:s.u'));
        }
    }

    private function sort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        if (!in_array($field, ['created_at', 'updated_at', 'consignment_number', 'current_status'], true)) {
            $field = 'created_at';
            $direction = 'desc';
        }
        return [$field, $direction];
    }

    public function findVisible(string $hqId, array $nodeIds, string $consignmentId): ?object
    {
        return $this->visibleQuery($hqId, $nodeIds)->where('c.consignment_id', $consignmentId)->first();
    }

    public function lockVisible(string $hqId, array $nodeIds, string $consignmentId): ?object
    {
        return $this->visibleQuery($hqId, $nodeIds)->where('c.consignment_id', $consignmentId)->lockForUpdate()->first();
    }

    public function activeNodeExists(string $nodeId, string $hqId): bool
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->exists();
    }

    public function lockParcels(string $hqId, string $consignmentId): array
    {
        return ParcelRecord::query()->toBase()->where('hq_id', $hqId)->where('consignment_id', $consignmentId)->orderBy('parcel_number')->lockForUpdate()->get()->all();
    }

    public function offeringEvidence(string $offeringVersionId, string $hqId): ?object
    {
        return DB::table('service_offering_versions as ov')->join('service_type_versions as stv', 'stv.service_type_version_id', '=', 'ov.service_type_version_id')->join('shipping_method_versions as smv', 'smv.shipping_method_version_id', '=', 'ov.shipping_method_version_id')->where('ov.service_offering_version_id', $offeringVersionId)->where(fn(Builder $query) => $query->whereNull('ov.hq_id')->orWhere('ov.hq_id', $hqId))->first(['ov.labels as offering_labels', 'stv.labels as service_type_labels', 'smv.labels as shipping_method_labels']);
    }

    public function parcelsInNumberOrder(string $hqId, string $id): array
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $id])->orderBy('parcel_number')->get()->all();
    }

    public function pricingVersions(string $hqId, string $id): array
    {
        return ConsignmentPricingVersionRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $id])->orderByDesc('version_number')->get()->all();
    }

    public function chargeLines(string $pricingVersionId, string $hqId): array
    {
        return ConsignmentPricingChargeLineRecord::query()->toBase()->where(['hq_id' => $hqId, 'pricing_version_id' => $pricingVersionId])->orderBy('line_number')->get()->all();
    }

    public function statusHistory(string $hqId, string $id): array
    {
        return StatusEventRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $id])->orderBy('created_at')->orderByRaw('event_sequence IS NULL')->orderBy('event_sequence')->orderBy('status_event_id')->get()->all();
    }

    public function auditHistory(string $hqId, string $id): array
    {
        return DB::table('audit_events')->where(['hq_id' => $hqId, 'target_type' => 'CONSIGNMENT', 'target_id' => $id])->orderBy('created_at')->get()->all();
    }

    public function custodyHistory(string $hqId, string $id): array
    {
        return CustodyEventRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $id])->orderBy('created_at')->orderByRaw('event_sequence IS NULL')->orderBy('event_sequence')->orderBy('custody_event_id')->get()->all();
    }

    public function latestRoutePlan(string $hqId, string $id): ?object
    {
        return DB::table('route_plans')->where(['hq_id' => $hqId, 'consignment_id' => $id])->orderByDesc('created_at')->first();
    }

    public function routeLegs(string $planId): array
    {
        return DB::table('route_plan_legs as l')->join('nodes as o', 'o.node_id', '=', 'l.origin_node_id')->join('nodes as d', 'd.node_id', '=', 'l.destination_node_id')->where('l.route_plan_id', $planId)->orderBy('l.leg_order')->get([
            'l.*',
            'o.node_code as origin_code',
            'o.node_title as origin_title',
            'd.node_code as destination_code',
            'd.node_title as destination_title',
        ])->all();
    }

    public function relatedManifests(string $hqId, string $id): array
    {
        return DB::table('manifests as m')->join('manifest_parcels as mp', 'mp.manifest_id', '=', 'm.manifest_id')->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')->where(['m.hq_id' => $hqId, 'p.consignment_id' => $id])->distinct()->orderBy('m.created_at')->get([
            'm.manifest_id',
            'm.manifest_number',
            'm.manifest_status',
            'm.manifest_type',
            'm.operational_context_type',
            'm.context_key',
            'm.node_id',
            'm.origin_node_id',
            'm.destination_node_id',
            'm.route_plan_id',
            'm.route_plan_leg_id',
            'm.route_definition_version_id',
            'm.route_definition_version_leg_id',
            'm.assigned_driver_id',
            'm.assigned_vehicle_id',
            'm.state',
            'm.operation_recorded_at',
            'm.closed_at',
            'm.created_at',
        ])->all();
    }

    public function manifestOutcomes(array $manifestIds, string $hqId, string $id): array
    {
        return DB::table('manifest_parcels as mp')->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')->where('mp.hq_id', $hqId)->where('p.consignment_id', $id)->whereIn('mp.manifest_id', $manifestIds)->orderBy('mp.created_at')->get([
            'mp.manifest_id',
            'mp.manifest_parcel_id',
            'mp.parcel_id',
            'mp.manifest_parcel_status',
            'mp.failure_code',
            'mp.source_status',
            'mp.route_plan_id',
            'mp.route_plan_leg_id',
            'mp.route_definition_version_id',
            'mp.route_definition_version_leg_id',
            'mp.assigned_driver_id',
            'mp.assigned_vehicle_id',
            'mp.evidence_recorded_at',
        ])->all();
    }

    public function parcels(string $hqId, string $consignmentId): array
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->get()->all();
    }

    public function node(string $hqId, string $nodeId): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId])->first();
    }

    public function draftParcels(string $hqId, string $consignmentId): array
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->orderBy('parcel_number')->get()->all();
    }

    public function insertConsignment(array $attributes): void
    {
        ConsignmentRecord::query()->toBase()->insert($attributes);
    }

    public function insertParcel(array $attributes): void
    {
        ParcelRecord::query()->toBase()->insert($attributes);
    }

    public function appendCustodyEvent(array $attributes): void
    {
        CustodyEventRecord::query()->toBase()->insert($attributes);
    }

    public function insertPricingVersion(array $attributes): void
    {
        ConsignmentPricingVersionRecord::query()->toBase()->insert($attributes);
    }

    public function insertPricingChargeLine(array $attributes): void
    {
        ConsignmentPricingChargeLineRecord::query()->toBase()->insert($attributes);
    }

    public function appendStatusEvent(array $attributes): void
    {
        StatusEventRecord::query()->toBase()->insert($attributes);
    }

    public function updateParcel(string $hqId, string $consignmentId, string $parcelId, array $changes): void
    {
        ParcelRecord::query()->toBase()->where('hq_id', $hqId)->where('consignment_id', $consignmentId)->where('parcel_id', $parcelId)->update($changes);
    }

    public function updateConsignmentVersion(string $hqId, string $consignmentId, int $expectedVersion, array $changes): void
    {
        ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'version' => $expectedVersion])->update($changes);
    }

    public function acceptPricing(string $hqId, string $consignmentId, array $changes): void
    {
        $changes['service_offering_id'] ??= DB::raw('service_offering_id');
        $changes['service_offering_version_id'] ??= DB::raw('service_offering_version_id');
        ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->update($changes);
    }

    public function nextCustodySequence(string $consignmentId): int
    {
        return (int) CustodyEventRecord::query()->toBase()->where('consignment_id', $consignmentId)->max('event_sequence') + 1;
    }

    public function nextStatusSequence(string $consignmentId): int
    {
        return (int) StatusEventRecord::query()->toBase()->where('consignment_id', $consignmentId)->max('event_sequence') + 1;
    }
}
