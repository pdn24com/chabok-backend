<?php

declare(strict_types=1);

namespace Modules\Dashboard\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Dashboard\Application\Repositories\DashboardRepository;
use Modules\Dashboard\Domain\DashboardMetricDefinitions;

final class SqlDashboardRepository implements DashboardRepository
{
    public function activeNode(string $hqId, string $nodeId): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->first(['node_id', 'node_code', 'node_title', 'node_type']);
    }

    public function consignmentCounts(string $hqId, string $nodeId): ?object
    {
        $selects = ['COUNT(*) AS total'];
        foreach (DashboardMetricDefinitions::CONSIGNMENT_STATUSES as $status) {
            $selects[] = "SUM(CASE WHEN current_status = '{$status}' THEN 1 ELSE 0 END) AS s_{$status}";
        }
        $active = implode("','", DashboardMetricDefinitions::ACTIVE_CONSIGNMENT_STATUSES);
        $selects[] = "SUM(CASE WHEN current_status IN ('{$active}') THEN 1 ELSE 0 END) AS active";
        return DB::table('consignments')->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])->selectRaw(implode(', ', $selects))->first();
    }

    public function manifestCounts(string $hqId, string $nodeId): ?object
    {
        return DB::table('manifests')->where(['hq_id' => $hqId, 'node_id' => $nodeId])->selectRaw("COUNT(*) AS total,\r\n                SUM(CASE WHEN state = 'DRAFT' THEN 1 ELSE 0 END) AS state_DRAFT,\r\n                SUM(CASE WHEN state = 'OPEN' THEN 1 ELSE 0 END) AS state_OPEN,\r\n                SUM(CASE WHEN state = 'CLOSED' THEN 1 ELSE 0 END) AS state_CLOSED,\r\n                SUM(CASE WHEN manifest_status = 'IR' THEN 1 ELSE 0 END) AS target_IR,\r\n                SUM(CASE WHEN manifest_status = 'OF' THEN 1 ELSE 0 END) AS target_OF,\r\n                SUM(CASE WHEN manifest_status = 'OD' THEN 1 ELSE 0 END) AS target_OD")->first();
    }

    public function failedManifestRowCount(string $hqId, string $nodeId): int
    {
        return DB::table('manifest_parcels as mp')->join('manifests as m', function ($join): void {
            $join->on('m.manifest_id', '=', 'mp.manifest_id')->on('m.hq_id', '=', 'mp.hq_id');
        })->where('m.hq_id', $hqId)->where('m.node_id', $nodeId)->where('mp.manifest_parcel_status', 'FAILED')->count();
    }

    public function driverCounts(string $hqId, string $nodeId): ?object
    {
        return DB::table('drivers')->where(['hq_id' => $hqId, 'home_node_id' => $nodeId, 'status' => 'ACTIVE'])->selectRaw("COUNT(*) AS total,\r\n                SUM(CASE WHEN availability_status = 'AVAILABLE' THEN 1 ELSE 0 END) AS status_AVAILABLE,\r\n                SUM(CASE WHEN availability_status = 'ON_MISSION' THEN 1 ELSE 0 END) AS status_ON_MISSION")->first();
    }

    public function exceptionConsignments(string $hqId, string $nodeId, int $limit): array
    {
        return DB::table('consignments')->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])->whereIn('current_status', ['NOK', 'NPU'])->orderByDesc('updated_at')->limit($limit)->get(['consignment_id', 'consignment_number', 'current_status', 'updated_at'])->all();
    }

    public function failedManifests(string $hqId, string $nodeId, int $limit): array
    {
        return DB::table('manifests as m')->join('manifest_parcels as mp', function ($join): void {
            $join->on('m.manifest_id', '=', 'mp.manifest_id')->on('m.hq_id', '=', 'mp.hq_id');
        })->where('m.hq_id', $hqId)->where('m.node_id', $nodeId)->whereIn('m.state', ['DRAFT', 'OPEN'])->where('mp.manifest_parcel_status', 'FAILED')->groupBy('m.manifest_id', 'm.manifest_number', 'm.state', 'm.updated_at')->select(['m.manifest_id', 'm.manifest_number', 'm.state', 'm.updated_at'])->orderByDesc('m.updated_at')->limit($limit)->get()->all();
    }

    public function consignmentUpdates(string $hqId, string $nodeId, int $limit): array
    {
        return DB::table('audit_events as a')->join('consignments as c', function ($join): void {
            $join->on('c.consignment_id', '=', 'a.target_id')->on('c.hq_id', '=', 'a.hq_id');
        })->where('a.hq_id', $hqId)->where('a.target_type', 'CONSIGNMENT')->where('c.pickup_node_id', $nodeId)->whereIn('a.action_key', ['CONSIGNMENT_CREATED', 'CONSIGNMENT_UPDATED'])->orderByDesc('a.created_at')->limit($limit)->get(['a.audit_id', 'a.action_key', 'a.created_at', 'c.consignment_id', 'c.consignment_number'])->all();
    }

    public function manifestUpdates(string $hqId, string $nodeId, int $limit, array $actionKeys): array
    {
        return DB::table('audit_events as a')->join('manifests as m', function ($join): void {
            $join->on('m.manifest_id', '=', 'a.target_id')->on('m.hq_id', '=', 'a.hq_id');
        })->where('a.hq_id', $hqId)->where('a.target_type', 'MANIFEST')->where('m.node_id', $nodeId)->whereIn('a.action_key', $actionKeys)->orderByDesc('a.created_at')->limit($limit)->get(['a.audit_id', 'a.action_key', 'a.created_at', 'm.manifest_id', 'm.manifest_number'])->all();
    }
}
