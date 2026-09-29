<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

interface PickupTaskRepositoryInterface
{
    public function findAtNode(?string $hqId, string $nodeId, string $taskId): ?PickupTaskRecord;

    public function lockAtNode(?string $hqId, string $nodeId, string $taskId): ?PickupTaskRecord;

    public function findForConsignment(?string $hqId, string $consignmentId): ?PickupTaskRecord;

    /** Tasks at a Node with the graph a task list renders. @return Collection<int, PickupTaskRecord> */
    public function listAtNode(?string $hqId, string $nodeId): Collection;

    /** One task at a Node with the graph a detail response renders. */
    public function findDetailAtNode(?string $hqId, string $nodeId, string $taskId): ?PickupTaskRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): void;

    /** Locks the tasks of the given Consignments in a stable order. @param list<string> $consignmentIds @return Collection<string, PickupTaskRecord> */
    public function lockByConsignmentKeyed(?string $hqId, array $consignmentIds): Collection;

    /** @param list<string> $consignmentIds @return Collection<int, PickupTaskRecord> */
    public function lockByConsignment(?string $hqId, array $consignmentIds): Collection;

    /** @param list<string> $consignmentIds @param array<string, mixed> $changes */
    public function completeOpenTasks(?string $hqId, array $consignmentIds, array $changes): void;

    /** @param list<array<string, mixed>> $rows */
    public function insert(array $rows): void;

    /** @param list<array<string, mixed>> $rows @param list<string> $columns */
    public function upsert(array $rows, array $columns): void;
}
