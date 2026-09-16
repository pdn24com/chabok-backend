<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Repositories;

/** Read-only, tenant-scoped projections; no cross-module entities escape this port. */

interface DashboardRepository
{
    public function activeNode(string $hqId, string $nodeId): ?object;

    public function consignmentCounts(string $hqId, string $nodeId): ?object;

    public function manifestCounts(string $hqId, string $nodeId): ?object;

    public function failedManifestRowCount(string $hqId, string $nodeId): int;

    public function driverCounts(string $hqId, string $nodeId): ?object;

    public function exceptionConsignments(string $hqId, string $nodeId, int $limit): array;

    public function failedManifests(string $hqId, string $nodeId, int $limit): array;

    public function consignmentUpdates(string $hqId, string $nodeId, int $limit): array;

    public function manifestUpdates(string $hqId, string $nodeId, int $limit, array $actionKeys): array;
}
