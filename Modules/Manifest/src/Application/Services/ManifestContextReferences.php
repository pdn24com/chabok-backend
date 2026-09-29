<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestContextReferencesInterface;
use Modules\Manifest\Application\Serialization\ManifestContextDocument;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class ManifestContextReferences implements ManifestContextReferencesInterface
{
    public function __construct(
        private ManifestContextDocument $manifestContextDocument,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestDirectoryReaderInterface $manifestDirectoryReader,
        private ManifestRouteAccessInterface $manifestRouteAccess,
    ) {}

    public function drivers(string $hq, array $accessibleNodeIds): array
    {
        return array_map(fn (DriverRecord $r): array => [
            ...$this->manifestContextDocument->driverResource($r),
            'capabilities' => $r->capabilities->map(fn ($capability): string => $capability->capability->value)->all(),
        ], $this->manifestTaskAccess->availableDrivers($hq, $accessibleNodeIds)->all());
    }

    public function vehicles(string $hq, string $node): array
    {
        return array_map(fn (VehicleRecord $r): array => [...$this->manifestContextDocument->vehicleResource($r), 'capabilities' => ['LINEHAUL', 'DELIVERY']], $this->manifestTaskAccess->availableVehicles($hq, $node)->all());
    }

    public function targetNodes(string $hq, array $accessibleNodeIds): array
    {
        return array_map(fn (NodeRecord $node): array => $this->manifestContextDocument->nodeResource($node), $this->manifestDirectoryReader->activeNodes($hq, $accessibleNodeIds)->all());
    }

    public function node(string $hq, string $node): NodeRecord
    {
        $r = $this->manifestDirectoryReader->activeNode($hq, $node);
        if ($r === null) {
            throw new ApiException(ApiErrorCode::CurrentNodeMismatch, 422, 'manifest.operational_node_is_unavailable');
        }

        return $r;
    }

    public function nullableNode(string $hq, ?string $id): ?array
    {
        return $id === null ? null : (($r = $this->manifestDirectoryReader->operationalContextNode($hq, $id)) ? $this->manifestContextDocument->nodeResource($r) : null);
    }

    public function routePlan(string $hq, ?string $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->manifestRouteAccess->planSummary($hq, $id);

        return $r ? [
            'route_plan_id' => (string) $r->route_plan_id,
            'consignment_id' => (string) $r->consignment_id,
            'consignment_number' => (string) $r->consignment->consignment_number,
            'route_definition_id' => (string) $r->route_definition_id,
            'route_code' => (string) $r->definition->route_code,
            'route_title' => (string) $r->definition->route_title,
            'status' => (string) $r->status,
            'version' => (int) $r->version,
        ] : null;
    }

    public function routeLeg(string $hq, ?string $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->manifestRouteAccess->leg($hq, $id);

        return $r ? [
            'route_plan_leg_id' => (string) $r->route_plan_leg_id,
            'leg_order' => (int) $r->leg_order,
            'status' => (string) $r->status,
            'origin_node_id' => (string) $r->origin_node_id,
            'destination_node_id' => (string) $r->destination_node_id,
        ] : null;
    }

    public function driver(string $hq, ?string $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->manifestTaskAccess->driver($hq, $id);

        return $r ? $this->manifestContextDocument->driverResource($r) : null;
    }

    public function vehicle(string $hq, ?string $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->manifestTaskAccess->vehicle($hq, $id);

        return $r ? $this->manifestContextDocument->vehicleResource($r) : null;
    }
}
