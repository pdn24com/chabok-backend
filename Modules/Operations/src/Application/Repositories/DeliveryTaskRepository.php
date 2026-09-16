<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

interface DeliveryTaskRepository
{
    public function forNode(?string $hqId, string $nodeId, array $filters): array;

    public function lockForConsignment(?string $hqId, string $consignmentId): ?object;

    public function tenantResolution(?string $hqId, string $consignmentId): ?object;

    public function lockAtConsignmentNode(?string $hqId, string $consignmentId, string $nodeId): ?object;

    public function lockLatestNokCase(?string $hqId, string $id): ?object;

    public function detail(?string $hqId, string $nodeId, string $id): ?object;

    public function parcels(?string $hqId, string $consignmentId): array;

    public function history(string $id): array;

    public function city(string $cityId): ?object;

    public function activeRoutePlanId(?string $hqId, string $consignmentId): ?string;

    public function lastLegOrder(string $planId): mixed;

    public function lockDriver(?string $hqId, string $driverId): ?object;

    public function driverCanDeliver(?string $hqId, string $driverId): bool;

    public function hasActiveAssignment(?string $hqId, string $driverId, ?string $currentTaskId): bool;

    public function hasManifestMission(?string $hqId, string $driverId, string $manifestId): bool;

    public function hasConflictingManifestAssignment(?string $hqId, string $driverId, string $currentTaskId, string $manifestId): bool;

    public function activeNode(string $hqId, string $nodeId): ?object;

    public function find(?string $hqId, string $nodeId, string $id): ?object;

    public function driverUser(?string $hqId, ?string $driverId): ?string;

    public function lock(?string $hqId, string $nodeId, string $id): ?object;

    public function resolution(string $consignmentId): ?object;

    public function routeProgress(string $consignmentId): array;

    public function lastHistorySequence(string $taskId): mixed;

    public function insert(array $attributes): void;

    public function appendExceptionHistory(array $attributes): void;

    public function insertResolution(array $attributes): void;

    public function insertRouteLeg(array $attributes): void;

    public function appendHistory(array $attributes): void;

    public function update(string $id, array $attributes): void;

    public function updateVersion(string $id, int $expected, array $attributes): void;

    public function updateExceptionCase(string $id, array $attributes): void;

    public function updateDriverAvailability(?string $hqId, string $driverId, string $expected, array $attributes): void;

    public function startRoutePlan(string $planId, \DateTimeImmutable $at): void;
}
