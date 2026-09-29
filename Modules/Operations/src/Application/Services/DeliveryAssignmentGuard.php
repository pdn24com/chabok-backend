<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\DeliveryAssignmentGuardInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final readonly class DeliveryAssignmentGuard implements DeliveryAssignmentGuardInterface
{
    public function __construct(
        private DriverRepositoryInterface $driverRepository,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
    ) {}

    public function version(DeliveryTaskRecord $task, int $expected): void
    {
        if ((int) $task->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.delivery_task_version_is_stale', details: ['current_version' => (int) $task->version]);
        }
    }

    public function eligibleDriver(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $driverId,
        ?string $currentTaskId = null,
    ): void {
        $driver = $this->driverRepository->lockByTenant($actor->hqId, $driverId);
        $capable = $driver !== null && $this->driverRepository->hasCapability($actor->hqId, $driverId, DriverCapability::Delivery->value);
        if ($driver === null || (string) $driver->home_node_id !== $nodeId || $driver->status->value !== 'ACTIVE' || $driver->availability_status->value !== 'AVAILABLE' || ! $capable) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.driver_must_belong_hq_home_node_be');
        }
        if ($this->deliveryTaskRepository->driverHasOtherOpenTask($actor->hqId, $driverId, $currentTaskId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.driver_is_already_assigned_active_delivery_task');
        }
    }

    public function eligibleDriverForManifest(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $driverId,
        string $manifestId,
        string $currentTaskId,
    ): void {
        $driver = $this->driverRepository->lockByTenant($actor->hqId, $driverId);
        $capable = $driver !== null && $this->driverRepository->hasCapability($actor->hqId, $driverId, DriverCapability::Delivery->value);
        $sameManifestMission = $driver !== null && $driver->availability_status->value === 'ON_MISSION'
            && $this->deliveryTaskRepository->driverOnManifestDelivery($actor->hqId, $driverId, $manifestId);
        if ($driver === null || (string) $driver->home_node_id !== $nodeId || $driver->status->value !== 'ACTIVE' || ! $sameManifestMission && $driver->availability_status->value !== 'AVAILABLE' || ! $capable) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'operations.delivery_driver_is_unavailable_manifest');
        }
        $conflict = $this->deliveryTaskRepository->driverHasConflictingTask($actor->hqId, $driverId, $currentTaskId, $manifestId);
        if ($conflict) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'operations.delivery_driver_has_another_active_assignment');
        }
    }
}
