<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestContextAccessInterface;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;

final readonly class ManifestContextAccess implements ManifestContextAccessInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestDirectoryReaderInterface $manifestDirectoryReader,
    ) {}

    public function accessibleNodeIds(AuthenticatedPrincipal $actor): array
    {
        return $this->accessContextResolver->resolve($actor)->accessibleNodeIds;
    }

    public function assertDriver(
        AuthenticatedPrincipal $actor,
        string $id,
        DriverCapability $capability,
    ): void {
        $hq = (string) $actor->hqId;
        $r = $this->manifestTaskAccess->driver($hq, $id);
        if ($r === null || ! in_array((string) $r->home_node_id, $this->accessibleNodeIds($actor), true)) {
            throw new ApiException(ApiErrorCode::DriverOutOfScope, 422, 'manifest.selected_driver_is_outside_authorized_operational_scope');
        }
        if (! $this->manifestTaskAccess->driverHasCapability($hq, $id, $capability)) {
            throw new ApiException(ApiErrorCode::DriverIncapable, 422, 'manifest.selected_driver_lacks_required_capability');
        }
        if ($r->status !== FleetStatus::Active || ! in_array($r->availability_status, [FleetAvailability::Available, FleetAvailability::OnMission], true)) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'manifest.selected_driver_is_unavailable');
        }
    }

    public function assertVehicle(
        string $hq,
        string $node,
        string $id,
    ): void {
        $r = $this->manifestTaskAccess->vehicle($hq, $id);
        if ($r === null || (string) $r->home_node_id !== $node) {
            throw new ApiException(ApiErrorCode::VehicleOutOfScope, 422, 'manifest.selected_vehicle_is_outside_authorized_node');
        }
        if ($r->status !== FleetStatus::Active || $r->availability_status !== FleetAvailability::Available) {
            throw new ApiException(ApiErrorCode::VehicleUnavailable, 422, 'manifest.selected_vehicle_is_unavailable');
        }
    }

    public function assertTargetNode(AuthenticatedPrincipal $actor, string $targetNodeId): void
    {
        $node = $this->manifestDirectoryReader->node($actor->hqId, $targetNodeId);
        if ($node === null || ! in_array($targetNodeId, $this->accessibleNodeIds($actor), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'manifest.selected_target_node_is_outside_authorized_operational');
        }
        if ((string) $node->status !== 'ACTIVE') {
            throw new ApiException(ApiErrorCode::CurrentNodeMismatch, 422, 'manifest.selected_target_node_is_inactive');
        }
    }
}
