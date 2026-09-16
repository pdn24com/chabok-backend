<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestContextReferences
{
    public function __construct(
        private \Modules\Manifest\Domain\ManifestContextShape $manifestContextShape,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
        private \Modules\Operations\Application\Contracts\ManifestDirectoryReader $directory,
        private \Modules\Operations\Application\Contracts\ManifestRouteAccess $routeState,
    )
    {
    }

    public function drivers(string $hq, array $accessibleNodeIds): array
    {
        return array_map(fn(object $r): array => [
            ...$this->manifestContextShape->driverResource($r),
            'capabilities' => $this->taskState->driverCapabilities($r->driver_id, $hq),
        ], $this->taskState->availableDrivers($hq, $accessibleNodeIds));
    }

    public function vehicles(string $hq, string $node): array
    {
        return array_map(fn(object $r): array => [...$this->manifestContextShape->vehicleResource($r), 'capabilities' => ['LINEHAUL', 'DELIVERY']], $this->taskState->availableVehicles($hq, $node));
    }

    public function targetNodes(string $hq, array $accessibleNodeIds): array
    {
        return array_map(fn(object $node): array => $this->manifestContextShape->nodeResource($node), $this->directory->activeNodes($hq, $accessibleNodeIds));
    }

    public function node(string $hq, string $node): object
    {
        $r = $this->directory->activeNode($hq, $node);
        if ($r === null) {
            throw new ApiException(ApiErrorCode::CurrentNodeMismatch, 422, 'The operational Node is unavailable.');
        }
        return $r;
    }

    public function nullableNode(string $hq, mixed $id): ?array
    {
        return $id === null ? null : (($r = $this->directory->OperationalContextNode($hq, $id)) ? $this->manifestContextShape->nodeResource($r) : null);
    }

    public function routePlan(string $hq, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->routeState->planSummary($hq, $id);
        return $r ? [
            'route_plan_id' => (string) $r->route_plan_id,
            'consignment_id' => (string) $r->consignment_id,
            'consignment_number' => (string) $r->consignment_number,
            'route_definition_id' => (string) $r->route_definition_id,
            'route_code' => (string) $r->route_code,
            'route_title' => (string) $r->route_title,
            'status' => (string) $r->status,
            'version' => (int) $r->version,
        ] : null;
    }

    public function routeLeg(string $hq, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->routeState->leg($hq, $id);
        return $r ? [
            'route_plan_leg_id' => (string) $r->route_plan_leg_id,
            'leg_order' => (int) $r->leg_order,
            'status' => (string) $r->status,
            'origin_node_id' => (string) $r->origin_node_id,
            'destination_node_id' => (string) $r->destination_node_id,
        ] : null;
    }

    public function driver(string $hq, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->taskState->driver($hq, $id);
        return $r ? $this->manifestContextShape->driverResource($r) : null;
    }

    public function vehicle(string $hq, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $r = $this->taskState->vehicle($hq, $id);
        return $r ? $this->manifestContextShape->vehicleResource($r) : null;
    }
}
