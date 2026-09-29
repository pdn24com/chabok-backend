<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Infrastructure\Persistence\Models\LastMileResolutionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

interface RoutePlanRepositoryInterface
{
    /** Plans visible at a Node, with everything a movement view renders. @return Collection<int, RoutePlanRecord> */
    public function visibleAtNode(string $hqId, string $nodeId): Collection;

    /** One visible plan at a Node, with the same relations loaded. */
    public function findVisibleAtNode(string $hqId, string $nodeId, string $planId): ?RoutePlanRecord;

    /** The plan still being executed for a Consignment, locked so two planners cannot both extend it. */
    public function lockOpenPlan(?string $hqId, string $consignmentId): ?RoutePlanRecord;

    public function findOpenPlan(?string $hqId, string $consignmentId): ?RoutePlanRecord;

    /** @param array<string, mixed> $attributes */
    public function createPlan(array $attributes): string;

    /** Raises the plan version alongside the change, which is how a stale writer is refused. @param array<string, mixed> $changes */
    public function revisePlan(string $planId, array $changes): void;

    public function nextLegOrder(string $planId): int;

    /** @param list<array<string, mixed>> $rows */
    public function insertLegs(array $rows): void;

    /** @param array<string, mixed> $attributes */
    public function insertResolutionEvidence(array $attributes): void;

    public function findLastMileResolution(?string $hqId, string $consignmentId): ?LastMileResolutionRecord;
}
