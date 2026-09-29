<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\AddAreaEdge;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use Modules\Organization\Application\Repositories\AreaHierarchyRepositoryInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;

final readonly class AddAreaEdgeHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ScopeTopologyInterface $scopeTopology,
        private ClockInterface $clock,
        private AreaRepositoryInterface $areaRepository,
        private AreaHierarchyRepositoryInterface $areaHierarchyRepository,
    ) {}

    public function handle(AddAreaEdgeCommand $command): void
    {
        $hqId = $command->hqId;
        $parentAreaId = $command->parentAreaId;
        $childAreaId = $command->childAreaId;
        $this->connection->transaction(function () use ($hqId, $parentAreaId, $childAreaId): void {
            $areas = $this->areaRepository->lockByIdsKeyedByAreaId($hqId, [$parentAreaId, $childAreaId]);
            $count = $areas->count();
            if ($count !== 2) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $cycle = in_array($parentAreaId, (new AreaHierarchy($this->scopeTopology->areaEdges($hqId)))->descendants($childAreaId), true);
            if ($parentAreaId === $childAreaId || $cycle) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.area_hierarchy_cycles_are_not_allowed');
            }
            $this->areaHierarchyRepository->create([
                'hq_id' => $hqId,
                'parent_area_id' => $areas[$parentAreaId]->id, 'child_area_id' => $areas[$childAreaId]->id,
                'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
        }, attempts: 3);

    }
}
