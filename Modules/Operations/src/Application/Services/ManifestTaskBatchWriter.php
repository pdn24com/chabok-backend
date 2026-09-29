<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Operations\Application\Contracts\ManifestTaskBatchWriterInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final readonly class ManifestTaskBatchWriter implements ManifestTaskBatchWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private PickupTaskRepositoryInterface $pickupTaskRepository,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
        private DriverRepositoryInterface $driverRepository,
        private ConsignmentRepositoryInterface $consignmentRepository,
    ) {}

    public function apply(string $hqId, string $nodeId, string $target, ?string $driverId, array $previousParcels): void
    {
        if ($previousParcels === []) {
            return;
        }
        $counts = [];
        $receivedDrivers = [];
        foreach ($previousParcels as $parcel) {
            $counts[$parcel->consignment_id] = ($counts[$parcel->consignment_id] ?? 0) + 1;
            if ($parcel->current_status === 'PU' && $parcel->current_custodian_id !== null) {
                $receivedDrivers[] = $parcel->current_custodian_id;
            }
        }
        if ($target === 'PD') {
            $this->assignPickups($hqId, $nodeId, $driverId, $counts);
        } elseif ($target === 'PU') {
            $this->pickupTaskRepository->completeOpenTasks($hqId, array_keys($counts),
                ['status' => 'COMPLETED', 'completed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
        } elseif ($target === 'NPU') {
            $this->failPickups($hqId, $counts);
        } elseif ($target === 'IR') {
            $this->releaseDrivers($hqId, $receivedDrivers);
        } elseif (in_array($target, ['OK', 'NOK'], true)) {
            $this->finishDeliveries($hqId, $counts, $target === 'OK');
        }
    }

    /** @param array<string, int> $counts */
    private function assignPickups(string $hqId, string $nodeId, ?string $driverId, array $counts): void
    {
        $existing = $this->pickupTaskRepository->lockByConsignmentKeyed($hqId, array_keys($counts));
        $inserts = [];
        $updates = [];
        foreach ($counts as $consignmentId => $count) {
            $task = $existing->get($consignmentId);
            $isNew = $task === null;
            if ($isNew) {
                $task = (new PickupTaskRecord)->forceFill(['hq_id' => $hqId,
                    'consignment_id' => $consignmentId, 'node_id' => $nodeId, 'version' => 0, 'created_at' => $this->clock->now()]);
            }
            $task->forceFill(['assigned_driver_id' => $driverId, 'status' => 'ASSIGNED', 'version' => $task->version + $count,
                'assigned_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            if ($isNew) {
                $inserts[] = $task->getAttributes();
            } else {
                $updates[] = $task->getAttributes();
            }
        }
        foreach (array_chunk($inserts, 100) as $batch) {
            $this->pickupTaskRepository->insert($batch);
        }
        foreach (array_chunk($updates, 100) as $batch) {
            $this->pickupTaskRepository->upsert($batch, ['assigned_driver_id', 'status', 'version', 'assigned_at', 'updated_at']);
        }
        $this->consignmentRepository->assignPickupDriver($hqId, array_keys($counts), $driverId);
        $this->driverRepository->startMission($hqId, $driverId, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
    }

    /** @param array<string, int> $counts */
    private function failPickups(string $hqId, array $counts): void
    {
        $tasks = $this->pickupTaskRepository->lockByConsignment($hqId, array_keys($counts));
        $updates = [];
        foreach ($tasks as $task) {
            $updates[] = $task->forceFill(['status' => 'FAILED', 'version' => $task->version + $counts[$task->consignment_id],
                'failed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()])->getAttributes();
        }
        foreach (array_chunk($updates, 100) as $batch) {
            $this->pickupTaskRepository->upsert($batch, ['status', 'version', 'failed_at', 'updated_at']);
        }
    }

    /** @param array<string, int> $counts */
    private function finishDeliveries(string $hqId, array $counts, bool $delivered): void
    {
        $tasks = $this->deliveryTaskRepository->lockByConsignment($hqId, array_keys($counts));
        $updates = [];
        $drivers = [];
        foreach ($tasks as $task) {
            $task->forceFill(['status' => $delivered ? 'COMPLETED' : 'FAILED', 'version' => $task->version + $counts[$task->consignment_id], 'updated_at' => $this->clock->now()]);
            if ($delivered) {
                $task->delivered_at = $this->clock->now();
            }
            $updates[] = $task->getAttributes();
            if ($task->assigned_driver_id !== null) {
                $drivers[] = $task->assigned_driver_id;
            }
        }
        $columns = $delivered ? ['status', 'version', 'updated_at', 'delivered_at'] : ['status', 'version', 'updated_at'];
        foreach (array_chunk($updates, 100) as $batch) {
            $this->deliveryTaskRepository->upsert($batch, $columns);
        }
        $this->releaseDrivers($hqId, $drivers);
    }

    /** @param list<string> $driverIds */
    private function releaseDrivers(string $hqId, array $driverIds): void
    {
        if ($driverIds === []) {
            return;
        }
        $this->driverRepository->endMissions($hqId, $driverIds, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
    }
}
