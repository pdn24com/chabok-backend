<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Operations\Application\Repositories\DeliveryTaskRepository;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final class EloquentDeliveryTaskRepository implements DeliveryTaskRepository
{
    public function forNode(?string $hqId, string $nodeId, array $filters): array
    {
        $query = DeliveryTaskRecord::query()->toBase()->from('delivery_tasks as task')->join('consignments as consignment', 'consignment.consignment_id', '=', 'task.consignment_id')->where(['task.hq_id' => $hqId, 'task.node_id' => $nodeId]);
        if (($filters['status'] ?? '') !== '') {
            $query->where('task.status', $filters['status']);
        }
        if (($filters['search'] ?? '') !== '') {
            $term = '%' . addcslashes(trim((string) $filters['search']), '%_\\') . '%';
            $query->where(fn($search) => $search->where('consignment.consignment_number', 'like', $term)->orWhere('consignment.receiver_contact_name', 'like', $term)->orWhere('consignment.receiver_mobile', 'like', $term));
        }
        return $query->orderByDesc('task.created_at')->get([
            'task.*',
            'consignment.consignment_number',
            'consignment.receiver_contact_name',
            'consignment.receiver_mobile',
            'consignment.receiver_address_text',
        ])->all();
    }

    public function lockForConsignment(?string $hqId, string $consignmentId): ?object
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
    }

    public function tenantResolution(?string $hqId, string $consignmentId): ?object
    {
        return DB::table('last_mile_resolution_evidence')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    public function lockAtConsignmentNode(?string $hqId, string $consignmentId, string $nodeId): ?object
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId])->lockForUpdate()->first();
    }

    public function lockLatestNokCase(?string $hqId, string $id): ?object
    {
        return DB::table('operational_exception_cases')->where(['hq_id' => $hqId, 'delivery_task_id' => $id, 'exception_type' => 'NOK'])->orderByDesc('created_at')->lockForUpdate()->first();
    }

    public function detail(?string $hqId, string $nodeId, string $id): ?object
    {
        return DeliveryTaskRecord::query()->toBase()->from('delivery_tasks as task')->join('consignments as consignment', 'consignment.consignment_id', '=', 'task.consignment_id')->join('nodes as node', 'node.node_id', '=', 'task.node_id')->leftJoin('drivers as driver', 'driver.driver_id', '=', 'task.assigned_driver_id')->where(['task.hq_id' => $hqId, 'task.node_id' => $nodeId, 'task.delivery_task_id' => $id])->first([
            'task.*',
            'consignment.consignment_number',
            'consignment.receiver_contact_name',
            'consignment.receiver_mobile',
            'consignment.receiver_address_text',
            'node.node_code',
            'node.node_title',
            'driver.driver_code',
            'driver.display_name as driver_name',
        ]);
    }

    public function parcels(?string $hqId, string $consignmentId): array
    {
        return DB::table('parcels')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->orderBy('parcel_number')->get()->map(fn($parcel): array => [
            'parcel_id' => (string) $parcel->parcel_id,
            'parcel_number' => (string) $parcel->parcel_number,
            'current_status' => (string) $parcel->current_status,
            'current_node_id' => $parcel->current_node_id,
            'current_custody_type' => (string) $parcel->current_custody_type,
            'current_custodian_id' => $parcel->current_custodian_id,
        ])->all();
    }

    public function history(string $id): array
    {
        return DB::table('delivery_task_history')->where('delivery_task_id', $id)->orderBy('event_sequence')->get()->map(fn($event): array => [
            'event_type' => (string) $event->event_type,
            'from_status' => $event->from_status,
            'to_status' => (string) $event->to_status,
            'attempt_number' => (int) $event->attempt_number,
            'assigned_driver_id' => $event->assigned_driver_id,
            'reason_code' => $event->reason_code,
            'safe_note' => $event->safe_note,
            'metadata' => $event->metadata ? json_decode((string) $event->metadata, true, flags: JSON_THROW_ON_ERROR) : null,
            'occurred_at' => $event->occurred_at,
        ])->all();
    }

    public function city(string $cityId): ?object
    {
        return DB::table('cities')->where('city_id', $cityId)->first();
    }

    public function activeRoutePlanId(?string $hqId, string $consignmentId): ?string
    {
        return DB::table('parcels')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->whereNotNull('active_route_plan_id')->value('active_route_plan_id');
    }

    public function lastLegOrder(string $planId): mixed
    {
        return DB::table('route_plan_legs')->where('route_plan_id', $planId)->max('leg_order');
    }

    public function lockDriver(?string $hqId, string $driverId): ?object
    {
        return DB::table('drivers')->where(['hq_id' => $hqId, 'driver_id' => $driverId])->lockForUpdate()->first();
    }

    public function driverCanDeliver(?string $hqId, string $driverId): bool
    {
        return DB::table('driver_capabilities')->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'capability' => 'DELIVERY'])->exists();
    }

    public function hasActiveAssignment(?string $hqId, string $driverId, ?string $currentTaskId): bool
    {
        $assigned = DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'assigned_driver_id' => $driverId])->whereIn('status', ['ASSIGNED', 'IN_PROGRESS']);
        if ($currentTaskId !== null) {
            $assigned->where('delivery_task_id', '!=', $currentTaskId);
        }
        return $assigned->exists();
    }

    public function hasManifestMission(?string $hqId, string $driverId, string $manifestId): bool
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'assigned_driver_id' => $driverId, 'manifest_id' => $manifestId, 'status' => 'IN_PROGRESS'])->exists();
    }

    public function hasConflictingManifestAssignment(?string $hqId, string $driverId, string $currentTaskId, string $manifestId): bool
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'assigned_driver_id' => $driverId])->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])->where('delivery_task_id', '!=', $currentTaskId)->where(fn($query) => $query->whereNull('manifest_id')->orWhere('manifest_id', '!=', $manifestId))->exists();
    }

    public function activeNode(string $hqId, string $nodeId): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->first();
    }

    public function find(?string $hqId, string $nodeId, string $id): ?object
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->first();
    }

    public function driverUser(?string $hqId, ?string $driverId): ?string
    {
        return DB::table('drivers')->where(['hq_id' => $hqId, 'driver_id' => $driverId])->value('user_id');
    }

    public function lock(?string $hqId, string $nodeId, string $id): ?object
    {
        return DeliveryTaskRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->lockForUpdate()->first();
    }

    public function resolution(string $consignmentId): ?object
    {
        return DB::table('last_mile_resolution_evidence')->where('consignment_id', $consignmentId)->first();
    }

    public function routeProgress(string $consignmentId): array
    {
        return DB::table('route_plan_legs as leg')->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')->join('nodes as origin', 'origin.node_id', '=', 'leg.origin_node_id')->join('nodes as destination', 'destination.node_id', '=', 'leg.destination_node_id')->where('plan.consignment_id', $consignmentId)->orderBy('leg.leg_order')->get([
            'leg.*',
            'origin.node_code as origin_code',
            'origin.node_title as origin_title',
            'destination.node_code as destination_code',
            'destination.node_title as destination_title',
        ])->map(fn($leg): array => [
            'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
            'leg_order' => (int) $leg->leg_order,
            'status' => (string) $leg->status,
            'origin_node' => [
                'node_id' => (string) $leg->origin_node_id,
                'node_code' => (string) $leg->origin_code,
                'node_title' => (string) $leg->origin_title,
            ],
            'destination_node' => [
                'node_id' => (string) $leg->destination_node_id,
                'node_code' => (string) $leg->destination_code,
                'node_title' => (string) $leg->destination_title,
            ],
            'routed_at' => $leg->routed_at,
            'received_at' => $leg->received_at,
        ])->all();
    }

    public function lastHistorySequence(string $taskId): mixed
    {
        return DB::table('delivery_task_history')->where('delivery_task_id', $taskId)->max('event_sequence');
    }

    public function insert(array $attributes): void
    {
        DeliveryTaskRecord::query()->toBase()->insert($attributes);
    }

    public function appendExceptionHistory(array $attributes): void
    {
        DB::table('operational_exception_history')->insert($attributes);
    }

    public function insertResolution(array $attributes): void
    {
        DB::table('last_mile_resolution_evidence')->insert($attributes);
    }

    public function insertRouteLeg(array $attributes): void
    {
        DB::table('route_plan_legs')->insert($attributes);
    }

    public function appendHistory(array $attributes): void
    {
        DB::table('delivery_task_history')->insert($attributes);
    }

    public function update(string $id, array $attributes): void
    {
        DeliveryTaskRecord::query()->toBase()->where('delivery_task_id', $id)->update($attributes);
    }

    public function updateVersion(string $id, int $expected, array $attributes): void
    {
        DeliveryTaskRecord::query()->toBase()->where(['delivery_task_id' => $id, 'version' => $expected])->update($attributes);
    }

    public function updateExceptionCase(string $id, array $attributes): void
    {
        DB::table('operational_exception_cases')->where('exception_case_id', $id)->update($attributes);
    }

    public function updateDriverAvailability(?string $hqId, string $driverId, string $expected, array $attributes): void
    {
        DB::table('drivers')->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'availability_status' => $expected])->update($attributes);
    }

    public function startRoutePlan(string $planId, \DateTimeImmutable $at): void
    {
        DB::table('route_plans')->where('route_plan_id', $planId)->update(['status' => 'IN_PROGRESS', 'version' => DB::raw('version + 1'), 'updated_at' => $at]);
    }
}
