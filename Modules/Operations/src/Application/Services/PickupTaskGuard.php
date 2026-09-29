<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\PickupTaskGuardInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final readonly class PickupTaskGuard implements PickupTaskGuardInterface
{
    public function __construct(
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function version(PickupTaskRecord $task, int $expected): void
    {
        if ((int) $task->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.pickup_task_version_is_stale', details: ['current_version' => (int) $task->version]);
        }
    }

    public function eligibleDriver(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $driverId,
        string $capability,
    ): void {
        $eligible = $this->driverRepository->eligibleAtNode($actor->hqId, $driverId, $nodeId, $capability);
        if (! $eligible) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.selected_driver_is_not_active_available_capable');
        }
    }
}
