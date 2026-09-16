<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PickupTaskGuard
{
    public function __construct(private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks)
    {
    }

    public function version(object $task, int $expected): void
    {
        if ((int) $task->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Pickup Task version is stale.', details: ['current_version' => (int) $task->version]);
        }
    }

    public function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, string $capability): void
    {
        $eligible = $this->tasks->eligibleDriver($actor->hqId, $nodeId, $driverId, $capability);
        if (!$eligible) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected driver is not active, available, capable, or in scope.');
        }
    }
}
