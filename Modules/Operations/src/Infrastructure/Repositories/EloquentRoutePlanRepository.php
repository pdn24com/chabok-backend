<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Domain\Enums\RoutePlanStatus;
use Modules\Operations\Infrastructure\Persistence\Models\LastMileResolutionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanResolutionEvidenceRecord;

final class EloquentRoutePlanRepository implements RoutePlanRepositoryInterface
{
    public function visibleAtNode(string $hqId, string $nodeId): Collection
    {
        return $this->visiblePlans($hqId, $nodeId)->whereHas('consignment')->orderByDesc('created_at')->get();
    }

    public function findVisibleAtNode(string $hqId, string $nodeId, string $planId): ?RoutePlanRecord
    {
        return $this->visiblePlans($hqId, $nodeId)->where('route_plan_id', $planId)->first();
    }

    public function lockOpenPlan(?string $hqId, string $consignmentId): ?RoutePlanRecord
    {
        return $this->openPlans($hqId, $consignmentId)->lockForUpdate()->first();
    }

    public function findOpenPlan(?string $hqId, string $consignmentId): ?RoutePlanRecord
    {
        return $this->openPlans($hqId, $consignmentId)->first();
    }

    public function createPlan(array $attributes): string
    {
        return (string) RoutePlanRecord::query()->forceCreate($attributes)->getKey();
    }

    public function revisePlan(string $planId, array $changes): void
    {
        RoutePlanRecord::query()->where('route_plan_id', $planId)->increment('version', 1, $changes);
    }

    public function nextLegOrder(string $planId): int
    {
        return (int) RoutePlanLegRecord::query()->where('route_plan_id', $planId)->max('leg_order') + 1;
    }

    public function insertLegs(array $rows): void
    {
        RoutePlanLegRecord::query()->insert($rows);
    }

    public function insertResolutionEvidence(array $attributes): void
    {
        (new RoutePlanResolutionEvidenceRecord)->forceFill($attributes)->save();
    }

    public function findLastMileResolution(?string $hqId, string $consignmentId): ?LastMileResolutionRecord
    {
        return LastMileResolutionRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    /** @return Builder<RoutePlanRecord> */
    private function openPlans(?string $hqId, string $consignmentId): Builder
    {
        return RoutePlanRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->whereIn('status', RoutePlanStatus::openValues());
    }

    /** @return Builder<RoutePlanRecord> */
    private function visiblePlans(string $hqId, string $nodeId): Builder
    {
        return RoutePlanRecord::query()->where('hq_id', $hqId)
            ->where(fn ($scope) => $scope
                ->whereHas('consignment', fn ($consignment) => $consignment->where('pickup_node_id', $nodeId))
                ->orWhereHas('legs', fn ($legs) => $legs->where('origin_node_id', $nodeId)->orWhere('destination_node_id', $nodeId)))
            ->with([
                'consignment', 'definition', 'definitionVersion',
                'evidence.policy', 'evidence.coverageVersion', 'evidence.gateway',
                'legs' => fn ($legs) => $legs->whereHas('originNode')->whereHas('destinationNode')->with(['originNode', 'destinationNode']),
            ]);
    }
}
