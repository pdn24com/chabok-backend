<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

interface PickupTaskRepository
{
    public function forNode(?string $hqId, string $nodeId): array;

    public function confirmedConsignment(?string $hqId, string $nodeId, string $consignmentId): ?object;

    public function forConsignment(?string $hqId, string $consignmentId): ?object;

    public function find(?string $hqId, string $nodeId, string $id): ?object;

    public function lock(?string $hqId, string $nodeId, string $id): ?object;

    public function eligibleDriver(?string $hqId, string $nodeId, string $driverId, string $capability): bool;

    public function driverUser(?string $hqId, ?string $driverId): ?string;

    public function consignment(?string $hqId, string $consignmentId): ?object;

    public function node(?string $hqId, string $nodeId): ?object;

    public function driver(?string $hqId, string $driverId): ?object;

    public function insert(array $attributes): void;

    public function update(string $id, array $attributes): void;
}
