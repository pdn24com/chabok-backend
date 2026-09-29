<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Dto\DeliveryTaskFiltersDto;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

interface DeliveryTaskRepositoryInterface
{
    public function findAtNode(?string $hqId, string $nodeId, string $taskId): ?DeliveryTaskRecord;

    public function lockAtNode(?string $hqId, string $nodeId, string $taskId): ?DeliveryTaskRecord;

    public function findDetailAtNode(?string $hqId, string $nodeId, string $taskId): ?DeliveryTaskRecord;

    public function lockForConsignment(?string $hqId, string $consignmentId): ?DeliveryTaskRecord;

    public function lockForConsignmentAtNode(?string $hqId, string $consignmentId, string $nodeId): ?DeliveryTaskRecord;

    /** @return Collection<int, DeliveryTaskRecord> */
    public function listAtNode(?string $hqId, string $nodeId, DeliveryTaskFiltersDto $filters): Collection;

    /** A driver already holds another open delivery, which is what stops a second concurrent assignment. */
    public function driverHasOtherOpenTask(?string $hqId, string $driverId, ?string $currentTaskId): bool;

    public function driverOnManifestDelivery(?string $hqId, string $driverId, ?string $manifestId): bool;

    public function driverHasConflictingTask(?string $hqId, string $driverId, string $currentTaskId, ?string $manifestId): bool;

    /** Applies a change only while the caller's expected version still holds. @param array<string, mixed> $changes */
    public function updateExpectedVersion(string $taskId, int $expectedVersion, array $changes): void;

    /** @param array<string, mixed> $changes */
    public function update(string $taskId, array $changes): void;

    /** @param list<string> $consignmentIds @return Collection<int, DeliveryTaskRecord> */
    public function lockByConsignment(?string $hqId, array $consignmentIds): Collection;

    /** @param list<array<string, mixed>> $rows @param list<string> $columns */
    public function upsert(array $rows, array $columns): void;

    public function nextHistorySequence(string $taskId): int;
}
