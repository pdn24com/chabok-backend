<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use Modules\Organization\Application\Contracts\HierarchyEditorInterface;
use Modules\Organization\Application\Repositories\AreaHierarchyRepositoryInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;

final readonly class HierarchyEditor implements HierarchyEditorInterface
{
    public function __construct(
        private ScopeTopologyInterface $scopeTopology,
        private ClockInterface $clock,
        private AreaRepositoryInterface $areaRepository,
        private AreaHierarchyRepositoryInterface $areaHierarchyRepository,
    ) {}

    public function replaceParent(
        string $hqId,
        string $areaId,
        ?string $parentId,
    ): void {
        $this->areaHierarchyRepository->deleteParentEdges($hqId, $areaId);
        if ($parentId === null) {
            return;
        }
        if ($parentId === $areaId || ! $this->areaRepository->activeExists($hqId, $parentId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.parent_area_is_invalid');
        }
        $cycle = in_array($parentId, (new AreaHierarchy($this->scopeTopology->areaEdges($hqId)))->descendants($areaId), true);
        if ($cycle) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.area_hierarchy_cycles_are_not_allowed');
        }
        $areas = $this->areaRepository->byIdsKeyedByAreaId($hqId, [$parentId, $areaId]);
        $child = $areas->get($areaId);
        $parent = $areas->get($parentId);
        if ($child === null || $parent === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $this->areaHierarchyRepository->create([
            'hq_id' => $hqId,
            'parent_area_id' => $parent->id, 'child_area_id' => $child->id,
            'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
        ]);
    }
}
