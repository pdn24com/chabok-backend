<?php

declare(strict_types=1);

namespace Modules\Dashboard\Infrastructure\Repositories;

use Carbon\CarbonImmutable;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Dashboard\Application\Dto\ConsignmentCountsDto;
use Modules\Dashboard\Application\Dto\DashboardUpdateDto;
use Modules\Dashboard\Application\Dto\DriverCountsDto;
use Modules\Dashboard\Application\Dto\ManifestCountsDto;
use Modules\Dashboard\Application\Repositories\DashboardRepositoryInterface;
use Modules\Dashboard\Domain\Definitions\DashboardMetricDefinitions;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    public function consignmentCounts(string $hqId, string $nodeId): ConsignmentCountsDto
    {
        // Aggregate in the database so dashboard memory does not grow with shipments.
        $groups = ConsignmentRecord::query()->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])
            ->select('current_status')->selectRaw('COUNT(*) AS aggregate_count')->groupBy('current_status')->get();
        $counts = array_fill_keys(DashboardMetricDefinitions::consignmentStatuses(), 0);
        $total = 0;
        $active = 0;
        foreach ($groups as $group) {
            $count = (int) $group->aggregate_count;
            $total += $count;
            if (array_key_exists($group->current_status, $counts)) {
                $counts[$group->current_status] = $count;
            }
            if (DashboardMetricDefinitions::isActiveConsignmentStatus($group->current_status)) {
                $active += $count;
            }
        }

        return new ConsignmentCountsDto($total, $active, $counts);
    }

    public function manifestCounts(string $hqId, string $nodeId): ManifestCountsDto
    {
        // Conditional aggregates count independent state and target dimensions in one scan.
        $counts = ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->selectRaw(
            'COUNT(*) AS total,
            COALESCE(SUM(state = ?), 0) AS draft,
            COALESCE(SUM(state = ?), 0) AS open_count,
            COALESCE(SUM(state = ?), 0) AS closed,
            COALESCE(SUM(manifest_status = ?), 0) AS inbound,
            COALESCE(SUM(manifest_status = ?), 0) AS outbound,
            COALESCE(SUM(manifest_status = ?), 0) AS delivery',
            [ManifestState::Draft->value, ManifestState::Open->value, ManifestState::Closed->value,
                ManifestTransition::Reception->value, ManifestTransition::OutboundConfirmation->value, ManifestTransition::DeliveryAssignment->value],
        )->first();
        $failedRows = ManifestParcelRecord::query()->where('hq_id', $hqId)->where('manifest_parcel_status', ManifestParcelStatus::Failed->value)
            ->whereHas('manifest', fn ($manifest) => $manifest->where(['hq_id' => $hqId, 'node_id' => $nodeId]))->count();

        return new ManifestCountsDto((int) $counts->total, (int) $counts->draft, (int) $counts->open_count,
            (int) $counts->closed, (int) $counts->inbound, (int) $counts->outbound, (int) $counts->delivery, $failedRows);
    }

    public function driverCounts(string $hqId, string $nodeId): DriverCountsDto
    {
        $counts = DriverRecord::query()->where(['hq_id' => $hqId, 'home_node_id' => $nodeId, 'status' => 'ACTIVE'])
            ->selectRaw("COUNT(*) AS total, COALESCE(SUM(availability_status = 'AVAILABLE'), 0) AS available, COALESCE(SUM(availability_status = 'ON_MISSION'), 0) AS on_mission")->first();

        return new DriverCountsDto((int) $counts->total, (int) $counts->available, (int) $counts->on_mission);
    }

    public function exceptionConsignments(string $hqId, string $nodeId, int $limit): array
    {
        return ConsignmentRecord::query()->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])
            ->whereIn('current_status', ConsignmentStatus::valuesOf(ConsignmentStatus::exceptions()))->orderByDesc('updated_at')->limit($limit)
            ->get(['consignment_id', 'consignment_number', 'current_status', 'updated_at'])->all();
    }

    public function failedManifests(string $hqId, string $nodeId, int $limit): array
    {
        return ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->whereIn('state', array_column(ManifestState::editable(), 'value'))
            ->whereHas('parcels', fn ($parcel) => $parcel->where('hq_id', $hqId)->where('manifest_parcel_status', ManifestParcelStatus::Failed->value))
            ->orderByDesc('updated_at')->limit($limit)->get(['manifest_id', 'manifest_number', 'state', 'updated_at'])->all();
    }

    public function consignmentUpdates(string $hqId, string $nodeId, int $limit): array
    {
        $events = AuditEventRecord::query()->where('hq_id', $hqId)->where('target_type', 'CONSIGNMENT')
            ->whereIn('target_id', ConsignmentRecord::query()->where(['hq_id' => $hqId, 'pickup_node_id' => $nodeId])->select('consignment_id'))
            ->whereIn('action_key', ['CONSIGNMENT_CREATED', 'CONSIGNMENT_UPDATED'])->orderByDesc('created_at')->limit($limit)->get();
        // Audit targets use different public key names per aggregate. One bounded lookup
        // avoids a custom polymorphic relationship and never loads a reference per event.
        $numbers = ConsignmentRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $events->pluck('target_id'))->pluck('consignment_number', 'consignment_id');
        $updates = [];
        foreach ($events as $event) {
            $updates[] = new DashboardUpdateDto($event->audit_id, $event->action_key, 'CONSIGNMENT', $event->target_id,
                $numbers[$event->target_id], CarbonImmutable::parse($event->created_at)->toDateTimeImmutable());
        }

        return $updates;
    }

    public function manifestUpdates(string $hqId, string $nodeId, int $limit): array
    {
        $events = AuditEventRecord::query()->where('hq_id', $hqId)->where('target_type', 'MANIFEST')
            ->whereIn('target_id', ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->select('manifest_id'))
            ->whereIn('action_key', DashboardMetricDefinitions::MANIFEST_AUDIT_ACTIONS)->orderByDesc('created_at')->limit($limit)->get();
        $numbers = ManifestRecord::query()->where('hq_id', $hqId)->whereIn('manifest_id', $events->pluck('target_id'))->pluck('manifest_number', 'manifest_id');
        $updates = [];
        foreach ($events as $event) {
            $updates[] = new DashboardUpdateDto($event->audit_id, $event->action_key, 'MANIFEST', $event->target_id,
                $numbers[$event->target_id], CarbonImmutable::parse($event->created_at)->toDateTimeImmutable());
        }

        return $updates;
    }
}
