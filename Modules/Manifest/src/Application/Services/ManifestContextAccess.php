<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestContextAccess
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
        private \Modules\Operations\Application\Contracts\ManifestDirectoryReader $directory,
    )
    {
    }

    public function accessibleNodeIds(AuthenticatedPrincipal $actor): array
    {
        return array_values(array_filter($this->authorization->resolve($actor)['accessible_node_ids'] ?? [], fn(mixed $id): bool => is_string($id) && $id !== ''));
    }

    public function assertDriver(AuthenticatedPrincipal $actor, string $id, string $capability): void
    {
        $hq = (string) $actor->hqId;
        $r = $this->taskState->driver($hq, $id);
        if ($r === null || !in_array((string) $r->home_node_id, $this->accessibleNodeIds($actor), true)) {
            throw new ApiException(ApiErrorCode::DriverOutOfScope, 422, 'The selected Driver is outside the authorized operational scope.');
        }
        if (!$this->taskState->driverHasCapability($hq, $id, $capability)) {
            throw new ApiException(ApiErrorCode::DriverIncapable, 422, 'The selected Driver lacks the required capability.');
        }
        if ((string) $r->status !== 'ACTIVE' || !in_array((string) $r->availability_status, ['AVAILABLE', 'ON_MISSION'], true)) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'The selected Driver is unavailable.');
        }
    }

    public function assertVehicle(string $hq, string $node, string $id): void
    {
        $r = $this->taskState->vehicle($hq, $id);
        if ($r === null || (string) $r->home_node_id !== $node) {
            throw new ApiException(ApiErrorCode::VehicleOutOfScope, 422, 'The selected Vehicle is outside the authorized Node.');
        }
        if ((string) $r->status !== 'ACTIVE' || (string) $r->availability_status !== 'AVAILABLE') {
            throw new ApiException(ApiErrorCode::VehicleUnavailable, 422, 'The selected Vehicle is unavailable.');
        }
    }

    public function assertTargetNode(AuthenticatedPrincipal $actor, string $targetNodeId): void
    {
        $node = $this->directory->node($actor->hqId, $targetNodeId);
        if ($node === null || !in_array($targetNodeId, $this->accessibleNodeIds($actor), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'The selected target Node is outside the authorized operational scope.');
        }
        if ((string) $node->status !== 'ACTIVE') {
            throw new ApiException(ApiErrorCode::CurrentNodeMismatch, 422, 'The selected target Node is inactive.');
        }
    }
}
