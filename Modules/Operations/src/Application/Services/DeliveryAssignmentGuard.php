<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class DeliveryAssignmentGuard
{
    public function __construct(private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks)
    {
    }

    public function version(object $task, int $expected): void
    {
        if ((int) $task->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Delivery Task version is stale.', details: ['current_version' => (int) $task->version]);
        }
    }

    public function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, ?string $currentTaskId = null): void
    {
        $driver = $this->tasks->lockDriver($actor->hqId, $driverId);
        $capable = $driver !== null && $this->tasks->driverCanDeliver($actor->hqId, $driverId);
        if ($driver === null || (string) $driver->home_node_id !== $nodeId || (string) $driver->status !== 'ACTIVE' || (string) $driver->availability_status !== 'AVAILABLE' || !$capable) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Driver must belong to this HQ and Home Node and be active, available, and DELIVERY-capable.');
        }
        if ($this->tasks->hasActiveAssignment($actor->hqId, $driverId, $currentTaskId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Driver is already assigned to an active Delivery Task.');
        }
    }

    public function eligibleDriverForManifest(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $driverId,
        string $manifestId,
        string $currentTaskId,
    ): void
    {
        $driver = $this->tasks->lockDriver($actor->hqId, $driverId);
        $capable = $driver !== null && $this->tasks->driverCanDeliver($actor->hqId, $driverId);
        $sameManifestMission = $driver !== null && (string) $driver->availability_status === 'ON_MISSION' && $this->tasks->hasManifestMission($actor->hqId, $driverId, $manifestId);
        if ($driver === null || (string) $driver->home_node_id !== $nodeId || (string) $driver->status !== 'ACTIVE' || !$sameManifestMission && (string) $driver->availability_status !== 'AVAILABLE' || !$capable) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'The Delivery Driver is unavailable for this Manifest.');
        }
        $conflict = $this->tasks->hasConflictingManifestAssignment($actor->hqId, $driverId, $currentTaskId, $manifestId);
        if ($conflict) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'The Delivery Driver has another active assignment.');
        }
    }
}
